<?php


namespace AllCoinTrade\Lambda;


use AllCoinCore\Database\DynamoDb\Exception\ItemReadException;
use AllCoinCore\Database\DynamoDb\Exception\ItemSaveException;
use AllCoinCore\DataMapper\EventPriceMapper;
use AllCoinCore\Lambda\AbstractLambda;
use AllCoinCore\Lambda\LambdaInterface;
use AllCoinTrade\Process\BinanceOrderBuyProcess;
use Psr\Log\LoggerInterface;

class BinanceOrderBuyLambda extends AbstractLambda implements LambdaInterface
{
    public function __construct(
        private BinanceOrderBuyProcess $binanceBuyOrderProcess,
        private EventPriceMapper $eventPriceMapper,
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @param array $event
     * @throws ItemReadException
     * @throws ItemSaveException
     */
    public function __invoke(array $event): void
    {
        $this->logger->debug('Receive event', [
            'event' => $event
        ]);

        $message = $this->getMessageFromEvent($event);
        $this->logger->debug('Message extract', [
            'message' => $message
        ]);

        if ($message) {
            $eventPrice = $this->eventPriceMapper->mapJsonToEvent($message);
            $this->binanceBuyOrderProcess->handle($eventPrice);
        }
    }
}
