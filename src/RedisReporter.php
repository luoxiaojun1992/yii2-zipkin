<?php

namespace Lxj\Yii2\Zipkin;

use RuntimeException;
use Zipkin\Recording\Span;
use Zipkin\Reporter;

final class RedisReporter implements Reporter
{
    const DEFAULT_OPTIONS = [
        'queue_name' => 'queue:zipkin:span',
        'connection' => 'zipkinRedis',
    ];

    /**
     * @var array
     */
    private $options;

    public function __construct(
        array $options = []
    ) {
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
    }

    /**
     * @param Span[] $spans
     * @return void
     */
    public function report(array $spans)
    {
        if (!$spans) {
            return;
        }

        $payload = json_encode(array_map(function (Span $span) {
            return $span->toArray();
        }, $spans));

        try {
            $this->enqueue($payload);
        } catch (RuntimeException $e) {
            //
        }
    }

    private function enqueue($payload)
    {
        $redisClient = $this->getRedisClient();
        if (is_null($redisClient)) {
            \Yii::error('Zipkin report error: redis client is null', 'zipkin');
            return;
        }

        if (empty($this->options['queue_name'])) {
            \Yii::error('Zipkin report error: redis queue name is empty', 'zipkin');
            return;
        }

        $redisClient->lpush($this->options['queue_name'], $payload);
    }

    private function getRedisClient()
    {
        if (!empty($this->options['connection'])) {
            $connectionName = $this->options['connection'];
            return \Yii::$app->{$connectionName};
        }

        return null;
    }
}
