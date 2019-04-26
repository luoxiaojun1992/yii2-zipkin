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

    private function needSample()
    {
        $apiPrefix = explode(',', \Yii::$app->zipkin->apiPrefix);
        $pathInfo = \Yii::$app->zipkin->formatHttpPath(\Yii::$app->request->getPathInfo());
        foreach ($apiPrefix as $prefix) {
            if (stripos($pathInfo, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if (!$this->needSample()) {
            parent::init();
            return;
        }

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
            $httpRequestBody = $this->tracer->convertToStr($yiiRequest->getRawBody());
            $httpRequestBodyLen = strlen($httpRequestBody);
            $this->tracer->addTag($this->span, Tracer::HTTP_REQUEST_BODY_SIZE, $httpRequestBodyLen);
            $this->tracer->addTag($this->span, Tracer::HTTP_REQUEST_BODY, $this->tracer->formatHttpBody(
                $httpRequestBody,
                $httpRequestBodyLen
            ));
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
        if (\Yii::$app->requestedRoute) {
            $this->span->setName($this->tracer->formatHttpPath(\Yii::$app->requestedRoute));
        } else {
            $this->span->setName(
                $this->tracer->formatRoutePath(
                    $this->tracer->formatHttpPath(\Yii::$app->request->getPathInfo())
                )
            );
        }
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
            if ($yiiResponse->getIsServerError()) {
                $this->tracer->addTag($this->span, ERROR, 'server error');
            } elseif ($yiiResponse->getIsClientError()) {
                $this->tracer->addTag($this->span, ERROR, 'client error');
            }
            $this->tracer->addTag($this->span, HTTP_STATUS_CODE, $yiiResponse->getStatusCode());
            $httpResponseBody = $this->tracer->convertToStr($yiiResponse->content);
            $httpResponseBodyLen = strlen($httpResponseBody);
            $this->tracer->addTag($this->span, Tracer::HTTP_RESPONSE_BODY_SIZE, $httpResponseBodyLen);
            $this->tracer->addTag($this->span, Tracer::HTTP_RESPONSE_BODY, $this->tracer->formatHttpBody(
                $httpResponseBody,
                $httpResponseBodyLen
            ));
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
            $this->span->setName($this->tracer->formatHttpPath(\Yii::$app->requestedRoute));
            if ($this->span->getContext()->isSampled()) {
                $this->finishSpanTag();
            }
            $this->finishSpan();
        }
    }
}
