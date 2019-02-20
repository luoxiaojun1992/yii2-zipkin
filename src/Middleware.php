<?php

namespace Lxj\Yii2\Zipkin;

use const Zipkin\Kind\SERVER;
use Zipkin\Span;
use const Zipkin\Tags\ERROR;
use const Zipkin\Tags\HTTP_HOST;
use const Zipkin\Tags\HTTP_METHOD;
use const Zipkin\Tags\HTTP_PATH;
use const Zipkin\Tags\HTTP_STATUS_CODE;

/**
 * Trait Middleware
 * @package Lxj\Yii2\Zipkin
 */
trait Middleware
{
    /** @var Tracer */
    private $tracer;

    private $startMemory = 0;

    /** @var Span */
    private $span;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        set_exception_handler(function (\Exception $exception) {
            $this->finishSpanTag();

            $this->span->tag(ERROR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());

            $this->finishSpan();

            \Yii::$app->getErrorHandler()->handleException($exception);
        });

        $this->tracer = \Yii::$app->zipkin;

        $this->startSpan();

        if ($this->span->getContext()->isSampled()) {
            $yiiRequest = \Yii::$app->request;
            $this->span->tag(HTTP_HOST, $this->tracer->formatHttpHost($yiiRequest->getHostInfo()));
            $this->span->tag(HTTP_PATH, $this->tracer->formatHttpPath($yiiRequest->getPathInfo()));
            $this->span->tag(HTTP_METHOD, $yiiRequest->getMethod());
            $this->span->tag(Tracer::HTTP_REQUEST_BODY, $yiiRequest->getRawBody());
            $this->span->tag(Tracer::HTTP_REQUEST_HEADERS, json_encode($yiiRequest->getHeaders()->toArray(), JSON_UNESCAPED_UNICODE));
            $this->span->tag(
                Tracer::HTTP_REQUEST_PROTOCOL_VERSION,
                $this->tracer->formatHttpProtocolVersion($_SERVER['SERVER_PROTOCOL'])
            );
            $this->span->tag(Tracer::HTTP_REQUEST_SCHEME, $yiiRequest->getIsSecureConnection() ? 'https' : 'http');
        }

        parent::init();
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    private function startSpan()
    {
        $parentContext = $this->tracer->getParentContext();

        $this->span = $this->tracer->getSpan($parentContext);
        $this->span->setName('Server recv:');
        $this->span->setKind(SERVER);
        $this->span->start();
        $this->tracer->rootContext = $this->span->getContext();

        if ($this->span->getContext()->isSampled()) {
            $this->startMemory = memory_get_usage();
            $this->tracer->beforeSpanTags($this->span);
        }
    }

    private function finishSpanTag()
    {
        if ($this->span->getContext()->isSampled()) {
            $yiiResponse = \Yii::$app->response;
            if ($yiiResponse) {
                $this->span->tag(HTTP_STATUS_CODE, $yiiResponse->getStatusCode());
                $this->span->tag(Tracer::HTTP_RESPONSE_BODY, is_string($yiiResponse->content) ? $yiiResponse->content : '');
                $this->span->tag(Tracer::HTTP_RESPONSE_HEADERS, json_encode($yiiResponse->getHeaders()->toArray(), JSON_UNESCAPED_UNICODE));
                $this->span->tag(
                    Tracer::HTTP_RESPONSE_PROTOCOL_VERSION,
                    $this->tracer->formatHttpProtocolVersion($yiiResponse->version)
                );
            }
        }
    }

    private function finishSpan()
    {
        $this->span->tag(Tracer::RUNTIME_MEMORY, round((memory_get_usage() - $this->startMemory) / 1000000, 2) . 'MB');
        $this->tracer->afterSpanTags($this->span);

        $this->span->finish();
        $this->tracer->flushTracer();

    }

    /**
     * @param \yii\base\Action $action
     * @param mixed $result
     * @return mixed
     */
    public function afterAction($action, $result)
    {
        $result = parent::afterAction($action, $result);

        if ($this->span->getContext()->isSampled()) {
            $yiiResponse = \Yii::$app->response;
            if ($yiiResponse->getIsServerError()) {
                $this->span->tag(ERROR, 'server error');
            } elseif ($yiiResponse->getIsClientError()) {
                $this->span->tag(ERROR, 'client error');
            }
        }

        $this->finishSpanTag();

        $this->finishSpan();

        return $result;
    }
}
