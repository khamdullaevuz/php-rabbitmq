<?php

return [
  'queues' => [
      'erp', 'pos'
  ],
  'default' => 'erp',
  'dead_letter_queue' => 'dead_letter_queue',
];