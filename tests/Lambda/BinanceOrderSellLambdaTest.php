<?php


namespace Test\Lambda;


use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\DataMapper\EventOrderMapper;
use AllCoinCore\Model\EventOrder;
use AllCoinTrade\Process\BinanceOrderSellProcess;
use AllCoinTrade\Lambda\BinanceOrderSellLambda;
use Psr\Log\LoggerInterface;
use Test\TestCase;

class BinanceOrderSellLambdaTest extends TestCase
{
    private BinanceOrderSellLambda $binanceOrderSellLambda;

    private BinanceOrderSellProcess $binanceOrderSellProcess;
    private EventOrderMapper $eventOrderMapper;

    public function setUp(): void
    {
        $this->binanceOrderSellProcess = $this->createMock(BinanceOrderSellProcess::class);
        $this->eventOrderMapper = $this->createMock(EventOrderMapper::class);

        $this->binanceOrderSellLambda = new BinanceOrderSellLambda(
            $this->binanceOrderSellProcess,
            $this->eventOrderMapper,
            $this->createMock(LoggerInterface::class)
        );
    }

    /**
     * @throws ItemSaveException
     */
    public function testInvokeWithNoMessageShouldStop(): void
    {
        $event = [];

        $this->eventOrderMapper->expects($this->never())->method('mapJsonToEvent');
        $this->binanceOrderSellProcess->expects($this->never())->method('handle');

        $this->binanceOrderSellLambda->__invoke($event);
    }

    /**
     * @throws ItemSaveException
     */
    public function testInvokeShouldBeOk(): void
    {
        $message = 'foo';

        $event = [
            'Records' => [
                [
                    'Sns' => [
                        'Message' => $message
                    ]
                ]
            ]
        ];

        $eventOrder = $this->createMock(EventOrder::class);

        $this->eventOrderMapper->expects($this->once())
            ->method('mapJsonToEvent')
            ->with($message)
            ->willReturn($eventOrder);

        $this->binanceOrderSellProcess->expects($this->once())
            ->method('handle')
            ->with($eventOrder);

        $this->binanceOrderSellLambda->__invoke($event);
    }
}
