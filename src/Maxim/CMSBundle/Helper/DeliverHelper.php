<?php
/**
 * Author: Maxim
 * Date: 06/02/14
 * Time: 13:33
 * Property of MCSuite
 */

namespace Maxim\CMSBundle\Helper;


use Doctrine\ORM\EntityManager;
use Maxim\CMSBundle\Entity\Purchase;
use Maxim\CMSBundle\Event\MinecraftSendEvent;
use Maxim\CMSBundle\Exception\CommandExecutionException;
use Monolog\Logger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class DeliverHelper
{

    private $eventDispatcher;
    private $minecraft;
    private $entityManager;
    private $config;
    private $logger;

    public function __construct(EventDispatcherInterface $eventDispatcher, MinecraftHelper $minecraft, EntityManager $entityManager, Logger $logger, $config)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->minecraft = $minecraft;
        $this->entityManager = $entityManager;
        $this->config = $config;
        $this->logger = $logger;
    }

    public function deliver(Purchase $purchase)
    {
        $item = $purchase->getStoreItem();

        try
        {
            switch($item->getType()) {
                case "COMMAND" :
                    $this->eventDispatcher->dispatch("minecraft.send", new MinecraftSendEvent(array($purchase)));
                    break;
                default:
                    $options = array(\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION);
                    $pdo = new \PDO('mysql:host='.$this->config["host"].';dbname='.$this->config["database"], $this->config["username"], $this->config["password"], $options);
                    $pdo->query($this->minecraft->parseCommand($item->getCommand(), array("USER" => $purchase->getName())));
                    $pdo  = null;
                    $purchase->setStatus(Purchase::PURCHASE_COMPLETE);
                    $purchase->setItemDelivery(Purchase::ITEM_DELIVERY_SUCCESS);
                    $this->entityManager->flush();
            }
            return true;
        }
        catch(\PDOException $ex)
        {
            $purchase->setStatus(Purchase::PURCHASE_ERROR_SQL);
            $purchase->setItemDelivery(Purchase::ITEM_DELIVERY_FAILED);
        }
        catch(CommandExecutionException $ex)
        {
            $purchase->setStatus(Purchase::PURCHASE_ERROR_COMMAND);
            $purchase->setItemDelivery(Purchase::ITEM_DELIVERY_FAILED);
        }
        catch(\Exception $ex)
        {
            $purchase->setStatus(Purchase::PURCHASE_ERROR_UNKNOWN);
            $purchase->setItemDelivery(Purchase::ITEM_DELIVERY_FAILED);
        }

        $this->entityManager->flush();
        return false;
    }
} 