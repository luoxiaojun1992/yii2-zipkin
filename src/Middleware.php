<?php

namespace Lxj\Yii2\Zipkin;

use yii\base\Event;
use yii\web\Response;
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
        set_exception_handler([$this, 'handleException']);

        \Yii::$app->response->on(Response::EVENT_AFTER_SEND, function (Event $event) {
            $this->afterSendResponse();
        });

        $this->tracer = \Yii::$app->zipkin;

        $this->startSpan();

        if ($this->span->getContext()->isSampled()) {
            $yiiRequest = \Yii::$app->request;
            $this->tracer->addTag($this->span, HTTP_HOST, $this->tracer->formatHttpHost($yiiRequest->getHostInfo()));
            $this->tracer->addTag($this->span, HTTP_PATH, $this->tracer->formatHttpPath($yiiRequest->getPathInfo()));
            $this->tracer->addTag($this->span, Tracer::HTTP_QUERY_STRING, (string)$yiiRequest->getQueryString());
            $this->tracer->addTag($this->span, HTTP_METHOD, $yiiRequest->getMethod());
            $this->tracer->addTag($this->span, Tracer::HTTP_REQUEST_BODY, $this->tracer->formatHttpBody($yiiRequest->getRawBody()));
            $this->tracer->addTag($this->span, Tracer::HTTP_REQUEST_HEADERS, json_encode($yiiRequest->getHeaders()->toArray(), JSON_UNESCAPED_UNICODE));
            $this->tracer->addTag(
                $this->span,
                Tracer::HTTP_REQUEST_PROTOCOL_VERSION,
                $this->tracer->formatHttpProtocolVersion($_SERVER['SERVER_PROTOCOL'])
            );
            $this->tracer->addTag($this->span, Tracer::HTTP_REQUEST_SCHEME, $yiiRequest->getIsSecureConnection() ? 'https' : 'http');
        }

        parent::init();
    }

    /**
     * Start a trace
     *
     * @throws \yii\base\InvalidConfigException
     */
    private function startSpan()
    {
        $parentContext = $this->tracer->getParentContext();

        $this->span = $this->tracer->getSpan($parentContext);
        $this->span->setName('Server recv:' . $this->tracer->formatHttpPath(\Yii::$app->request->getPathInfo()));
        $this->span->setKind(SERVER);
        $this->span->start();
        $this->tracer->rootContext = $this->span->getContext();

        if ($this->span->getContext()->isSampled()) {
            $this->startMemory = memory_get_usage();
            $this->tracer->beforeSpanTags($this->span);
        }
    }

    /**
     * Add tags before finishing trace
     */
    private function finishSpanTag()
    {
        $yiiResponse = \Yii::$app->response;
        if ($yiiResponse) {
            $this->tracer->addTag($this->span, HTTP_STATUS_CODE, $yiiResponse->getStatusCode());
            $this->tracer->addTag($this->span, Tracer::HTTP_RESPONSE_BODY, $this->tracer->formatHttpBody($yiiResponse->content));
            $this->tracer->addTag($this->span, Tracer::HTTP_RESPONSE_HEADERS, json_encode($yiiResponse->getHeaders()->toArray(), JSON_UNESCAPED_UNICODE));
            $this->tracer->addTag(
                $this->span,
                Tracer::HTTP_RESPONSE_PROTOCOL_VERSION,
                $this->tracer->formatHttpProtocolVersion($yiiResponse->version)
            );
        }
        $this->tracer->addTag($this->span, Tracer::RUNTIME_MEMORY, round((memory_get_usage() - $this->startMemory) / 1000000, 2) . 'MB');
        $this->tracer->afterSpanTags($this->span);
    }

    /**
     * Finish a trace
     */
    private function finishSpan()
    {
        $this->span->finish();
        $this->tracer->flushTracer();

    }

    /**
     * Handler after sending response
     */
    private function afterSendResponse()
    {
        if ($this->span && $this->tracer) {
            if ($this->span->getContext()->isSampled()) {
                $this->finishSpanTag();
            }
            $this->finishSpan();
        }
    }

    /**
     * Exception can be handled exactly once
     *
     * @param \Exception $exception
     */
    public function handleException(\Exception $exception)
    {
        if ($this->span && $this->tracer) {
            if ($this->span->getContext()->isSampled()) {
                $this->tracer->addTag($this->span, ERROR, $exception->getMessage() . PHP_EOL . $exception->getTraceAsString());
            }
        }

        \Yii::$app->getErrorHandler()->handleException($exception);
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
                $this->tracer->addTag($this->span, ERROR, 'server error');
            } elseif ($yiiResponse->getIsClientError()) {
                $this->tracer->addTag($this->span, ERROR, 'client error');
            }
        }

        return $result;
    }
}