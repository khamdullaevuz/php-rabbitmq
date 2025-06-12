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

    private array $params = [];
    private string $method;
    private bool $isRpc = false;
    private mixed $response;

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

    public function publish(string $method = null, array $params = []): void
    {
        $this->doPublish($method, $params, false);
    }

    public function request(string $method = null, array $params = []): static
    {
        $this->isRpc = true;
        $this->method = $method;
        $this->params = $params;
        return $this;
    }

    public function getResult(): mixed
    {
        $this->response = null;
        $this->doPublish($this->method, $this->params, true);
        return $this->response;
    }

    /**
     * @throws Exception
     */
    private function doPublish(string $method, array $params, bool $isRpc): void
    {
        $connection = $this->connection;
        $channel = $connection->channel();
        $channel->queue_declare($this->queue, false, true, false, false);

        $properties = [];
        $correlationId = null;
        $callbackQueue = null;

        if ($isRpc) {
            [$callbackQueue, ,] = $channel->queue_declare('', false, true, true, true);
            $correlationId = Uuid::uuid4()->toString();

            $properties = [
                    'correlation_id' => $correlationId,
                    'reply_to'       => $callbackQueue,
            ];

            $channel->basic_consume($callbackQueue, '', false, true, false, false, function ($msg) use ($correlationId) {
                if ($msg->get('correlation_id') === $correlationId) {
                    $this->response = json_decode($msg->body, true);
                }
            });
        }

        $message = new AMQPMessage(json_encode([
                                                       'method' => $method,
                                                       'params' => $params,
                                               ]), $properties);

        $channel->basic_publish($message, '', $this->queue);

        if ($isRpc) {
            while (!$this->response) {
                $channel->wait();
            }
        } else {
            echo "Message published to queue {$this->queue}\n";
        }

        $channel->close();
        $connection->close();
    }

    public function consume(): void
    {
        $connection = $this->connection;
        $channel    = $connection->channel();

        $queues = $this->queues['queues'] ?? [$this->queue];

        foreach ($queues as $queue) {
            $channel->queue_declare($queue, false, true, false, false);

            $callback = function ($msg) use ($channel, $queue) {
                $data   = json_decode($msg->body, true);
                $method = $data['method'] ?? null;
                $params = $data['params'] ?? [];

                $event = $this->events[$method] ?? null;
                if (!$event) {
                    $msg->ack();
                    return;
                }

                $instance   = new $event['class']();
                $callMethod = $event['method'] ?? null;
                $dto        = $event['dto'] ?? null;

                $isRpc = $msg->has('reply_to') && $msg->has('correlation_id');

                try {
                    $result = $instance->$callMethod($dto::fromArray($params));
                } catch (Exception $exception) {
                    $errorPayload = [
                            'method' => $method,
                            'params' => $params,
                            'error'  => [
                                    'file'    => $exception->getFile(),
                                    'line'    => $exception->getLine(),
                                    'message' => $exception->getMessage(),
                                    'time'    => date('Y-m-d H:i:s'),
                            ],
                    ];

                    if ($isRpc) {
                        $result = $errorPayload;
                    } else {
                        $channel->queue_declare($this->queues['dead_letter_queue'], false, true, false, false);
                        $channel->basic_publish(
                                new AMQPMessage(json_encode($errorPayload)),
                                '',
                                $this->queues['dead_letter_queue']
                        );
                        $msg->ack();
                        return;
                    }
                }

                if ($isRpc) {
                    $reply = new AMQPMessage(
                            json_encode($result),
                            ['correlation_id' => $msg->get('correlation_id')]
                    );
                    $channel->basic_publish($reply, '', $msg->get('reply_to'));
                }

                $msg->ack();
            };

            $channel->basic_qos(0, 1, false);
            $channel->basic_consume($queue, '', false, false, false, false, $callback);
        }

        echo "Waiting for messages. To exit press CTRL+C\n";
        while ($channel->is_consuming()) {
            $channel->wait();
        }

        $channel->close();
        $connection->close();
    }
}