<?php

namespace App;

use Exception;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Ramsey\Uuid\Uuid;

class Rabbit
{
    private array                $queues = [];
    private ?string              $queue  = null;
    private array                $events = [];
    private AMQPStreamConnection $connection;

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->connection = new AMQPStreamConnection(
                'localhost', // RabbitMQ server host
                5672,        // RabbitMQ server port
                'guest',     // RabbitMQ username
                'guest'      // RabbitMQ password
        );
        $this->queues     = require __DIR__ . '/../config/queues.php';
        $this->events     = require __DIR__ . '/../config/events.php';
        $this->queue      = $this->queues['default'] ?? 'msgs';
    }

    /**
     * @throws Exception
     */
    public function consume(): void
    {
        $connection = $this->connection;
        $channel    = $connection->channel();
        $queues     = $this->queues['queues'];
        foreach ($queues as $queue) {
            $channel->queue_declare($queue, false, true, false, false, false, []);
            $callback = function ($msg) use ($channel, $queue) {
                $body       = json_decode($msg->body, true);
                $method     = $body['method'] ?? null;
                $params     = $body['params'] ?? [];
                $event      = $this->events[$method] ?? null;
                $instance   = new $event['class']();
                $callMethod = $event['method'] ?? null;
                try {
                    $instance->$callMethod(...$params);
                } catch (Exception $exception) {
                    $channel->queue_declare($this->queues['dead_letter_queue'], false, true, false, false, false, []);
                    $message = new AMQPMessage(
                            json_encode(
                                    [
                                            'method' => $method,
                                            'params' => $params,
                                            'error'  => [
                                                    'file'    => $exception->getFile(),
                                                    'line'    => $exception->getLine(),
                                                    'message' => $exception->getMessage(),
                                                    'time'    => date('Y-m-d H:i:s'),
                                            ],
                                    ]
                            )
                    );

                    $channel->basic_publish($message, '', $this->queues['dead_letter_queue']);
                }

                $msg->ack();
            };
            $channel->basic_consume($queue, '', false, false, false, false, $callback);
        }

        echo "Waiting for messages. To exit press CTRL+C\n";
        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    /**
     * @throws Exception
     */
    public function publish(string $method, array $params): void
    {
        $queue      = $this->queue;
        $connection = $this->connection;
        $channel    = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false, false, []);
        $msg = new AMQPMessage(json_encode([
                                                   'method' => $method,
                                                   'params' => $params,
                                           ]));

        $channel->basic_publish($msg, '', $queue);
        echo "Message published to queue {$queue}";
        $channel->close();
        $connection->close();
    }

    /**
     * @throws Exception
     */
    public function rpcConsume(): void
    {
        $queue       = $this->queue;
        $connection  = $this->connection;
        $channel     = $connection->channel();
        $channel->queue_declare($queue, false, true, false, false);

        $callback = function ($req) use ($channel) {
            $request = json_decode($req->body, true);

            $method     = $request['method'] ?? null;
            $params     = $request['params'] ?? [];
            $event      = $this->events[$method] ?? null;
            $instance   = new $event['class']();
            $callMethod = $event['method'] ?? null;

            try{
                $response   = $instance->$callMethod(...$params);
            }catch (Exception $exception){
                $response = [
                        'error' => [
                            'file'    => $exception->getFile(),
                            'line'    => $exception->getLine(),
                            'message' => $exception->getMessage(),
                            'time'    => date('Y-m-d H:i:s'),
                        ],
                    ];
            }

            $msg = new AMQPMessage(
                    json_encode($response),
                    ['correlation_id' => $req->get('correlation_id')]
            );

            $channel->basic_publish($msg, '', $req->get('reply_to'));
            $channel->basic_ack($req->get('delivery_tag'));
        };

        $channel->basic_qos(0, 1, false);
        $channel->basic_consume($queue, '', false, false, false, false, $callback);

        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }

    /**
     * @throws Exception
     */
    public function rpcPublish(string $method, array $params): mixed
    {
        $queue       = $this->queue;
        $connection  = $this->connection;
        $channel     = $connection->channel();
        [$callbackQueue, ,] = $channel->queue_declare("", false, true, true, true);

        $correlationId = Uuid::uuid4()->toString();

        $response = null;

        $channel->basic_consume($callbackQueue, '', false, true, false, false, function ($msg) use (
                &$response,
                $correlationId
        ) {
            if ($msg->get('correlation_id') === $correlationId) {
                $response = $msg->body;
            }
        });

        $msg = new AMQPMessage(
                json_encode([
                                    'method' => $method,
                                    'params' => $params,
                            ]),
                [
                        'correlation_id' => $correlationId,
                        'reply_to'       => $callbackQueue,
                ]
        );

        $channel->basic_publish($msg, '', $queue);

        while (!$response) {
            $channel->wait(); // Wait synchronously
        }

        $channel->close();
        $connection->close();

        return json_decode($response, true);
    }
}