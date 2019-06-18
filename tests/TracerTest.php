<?php

use Mockery as M;

class TracerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * Testing in web container
     *
     * @throws Exception
     */
    public function testWebTrace()
    {
        //Mock Yii2 Base Component
        require_once __DIR__ . '/Stubs/BaseComponentStub.php';

        //Mock HeaderCollection
        $headerCollection = M::mock('\\yii\\webHeaderCollection');
        $headerCollection->shouldReceive('has')->with('x-b3-sampled')
            ->andReturnFalse();
        $headerCollection->shouldReceive('has')->with('x-b3-flags')
            ->andReturnFalse();
        $headerCollection->shouldReceive('has')->with('x-b3-traceid')
            ->andReturnFalse();
        $headerCollection->shouldReceive('has')->with('x-b3-spanid')
            ->andReturnFalse();
        $headerCollection->shouldReceive('has')->with('x-b3-parentspanid')
            ->andReturnFalse();

        //Mock Request
        $request = M::mock('\\yii\\web\\Request');
        $request->shouldReceive('getHeaders')
            ->andReturn($headerCollection);
        $request->shouldReceive('getUserIp')
            ->andReturnNull();

        //Mock App
        $app = M::mock('\\yii\\web\\Application');
        $app->shouldReceive('getRequest')
            ->andReturn($request);
        $app->shouldReceive('getVersion')
            ->andReturn('1.0');

        //Mock Yii
        require_once __DIR__ . '/Stubs/YiiStub.php';
        M::mock('alias:\\Yii', YiiStub::class);
        \Yii::$app = $app;

        $tracer = new \Lxj\Yii2\Zipkin\Tracer();
        $tracer->sampleRate = 1;

        $this->assertTrue($tracer->serverSpan('unit-test', function (\Zipkin\Span $span) use ($tracer) {
            $this->assertTrue($tracer->clientSpan('unit-test-sub', function (\Zipkin\Span $span) {
                return true;
            }));

            return true;
        }, true));
    }

    /**
     * Testing in console environment
     *
     * @throws Exception
     */
    public function testConsoleTrace()
    {
        //Mock Yii2 Base Component
        require_once __DIR__ . '/Stubs/BaseComponentStub.php';

        //Mock App
        $app = M::mock('\\yii\\console\\Application');
        $app->shouldReceive('getVersion')
            ->andReturn('1.0');

        //Mock Yii
        require_once __DIR__ . '/Stubs/YiiStub.php';
        M::mock('alias:\\Yii', YiiStub::class);
        \Yii::$app = $app;

        $tracer = new \Lxj\Yii2\Zipkin\Tracer();
        $tracer->sampleRate = 1;

        $this->assertTrue($tracer->serverSpan('unit-test', function (\Zipkin\Span $span) use ($tracer) {
            $this->assertTrue($tracer->clientSpan('unit-test-sub', function (\Zipkin\Span $span) {
                return true;
            }));

            return true;
        }, true));
    }
}
