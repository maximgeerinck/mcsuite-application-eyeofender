<?php
namespace Maxim\CMSBundle\Controller;

use Maxim\CMSBundle\Event\StoreEvent;
use Maxim\CMSBundle\Event\UserEvent;
use Maxim\CMSBundle\Helper\RESTHelper;
use Maxim\CMSBundle\Listeners\StoreListener;
use Maxim\CMSBundle\Listeners\UserListener;
use Maxim\CMSBundle\StoreEvents;
use Maxim\CMSBundle\UserEvents;
use Payum\Paypal\ExpressCheckout\Nvp\Bridge\Buzz\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Yaml\Yaml;
use Maxim\CMSBundle\Entity\Purchase;
use Maxim\CMSBundle\Entity\StoreItem;
use Maxim\CMSBundle\Entity\PaypalExpressPaymentDetails;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\Range;
use Sensio\Bundle\FrameworkExtraBundle\Configuration as Extra;
use Payum\Request\BinaryMaskStatusRequest;
use Payum\Registry\RegistryInterface;
use Payum\Paypal\ExpressCheckout\Nvp\Api;
use Payum\Paypal\ExpressCheckout\Nvp\Model\PaymentDetails;
use Payum\Bundle\PayumBundle\Service\TokenManager;

class StoreController extends ModuleController
{
	public function indexAction()
	{
		# basic settings
        $donate_config  = $this->container->getParameter('maxim_cms.store');
    	$em = $this->getDoctrine()->getManager();
        $websiteid = $this->container->getParameter('website');

        $storeItems = $em->getRepository("MaximCMSBundle:StoreItem")->findAllVisibleOrderedByName($websiteid);
        $storeCategories = $em->getRepository("MaximCMSBundle:StoreCategory")->findAllVisibleOrderedByName($websiteid);

        foreach($storeItems as $item)
        {
            if($item)
            {
                $data['items'][] = array(
                    "id"            => $item->getId(),
                    "name"          => $item->getName(),
                    "description"   => $item->getDescription(),
                    "amount"        => number_format(round($item->getAmount() * (1 -($item->getReduction() / 100)), 2), 2),
                    "image"         => $item->getImage(),
                    "category"       => $item->getStoreCategory(),
                );
            }
        }

        $data['topitems'] = $em->getRepository('MaximCMSBundle:Purchase')->findAllTopPurchases(5);
		$data['config'] = array("currency" => $donate_config['currency_symbol']);
        $data['categories'] = $storeCategories;

        return $this->render('MaximCMSBundle:pages:store/view.html.twig', $data);
	}
	
	public function step2Action(Request $request)
	{
		$em = $this->getDoctrine()->getManager();

		$id = $request->request->get('_btnBuy');

        $username 	= $request->request->get('_ign');

        return $this->render('MaximCMSBundle:pages:store/step2.html.twig', array(
            'item'      =>   $id,
            'ign'       =>   $username
        ));
	}

    public function confirmAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $donate_config  = $this->container->getParameter('maxim_cms.store');
        $id 		= $request->request->get('_previousChoice');
        $username 	= $request->request->get('_ign');
        //$terms      = $request->request->get('donation_terms');
        $item       = $em->getRepository("MaximCMSBundle:StoreItem")->findOneBy(array("id" => $id));

        if(!$item)
        {
            throw $this->createNotFoundException("Could not find the request item");
        }

        ###################
        # EVENT DISPATCHED
        ###################
        $dispatcher = $this->get('event_dispatcher');
        //add listeners
        $userListener  = new UserListener($em);

        $user = $this->getUser();
        $userEvent = new UserEvent($user);

        // dispatch update event
        $dispatcher->dispatch(UserEvents::USER_UPDATE, $userEvent);

        $custom = $username.'|'.date("Y-m-d H:i:s").'|'.$id.'|'.$_SERVER['REMOTE_ADDR'].'|'.$user->getEmail();

        $data['item'] =  array(
            "id"            => $item->getId(),
            "name"          => $item->getName(),
            "description"   => $item->getDescription(),
            "amount"        => number_format(round(($item->getAmount() * (1 -($item->getReduction() / 100))), 2), 2),
            "image"         => $item->getImage(),
            "category"      => $item->getStoreCategory(),
            "tax"           => $item->getTax()
        );

        $data['user'] = array(
            "username"              => $username,
            "custom"                => $custom,
            "ign"                   => $username
        );
        $data['config'] = $donate_config;

        return $this->render('MaximCMSBundle:pages:store/confirm.html.twig', $data);
    }

    public function prepareAction($purchase)
    {
        $paymentName = 'paypal_express_checkout_plus_doctrine';

        $item = $purchase->getStoreItem();


        $data = array(
            "currency"  =>  "GBP",
        );
        $storage = $this->get('payum')->getStorageForClass(
            'Maxim\CMSBundle\Entity\PaymentDetails',
            $paymentName
        );

        $total = $item->getAmount() * 1;

        $tax = 0;
        if($item->getTax() > 0) {
            $tax = ($total * ($item->getTax() / 100));
        }


        /** @var $paymentDetails PaymentDetails */
        $paymentDetails = $storage->createModel();
        $paymentDetails['PAYMENTREQUEST_0_CURRENCYCODE'] = $data['currency'];
        $paymentDetails['PAYMENTREQUEST_0_ITEMAMT']      = number_format($total, 2);
        $paymentDetails['PAYMENTREQUEST_0_TAXAMT']       = number_format($tax, 2);
        $paymentDetails['PAYMENTREQUEST_0_AMT']          = number_format(($total + $tax), 2);
        //$paymentDetails['PAYMENTREQUEST_0_ITEMCATEGORY'] = Api::PAYMENTREQUEST_ITERMCATEGORY_PHYSICAL;
        //$paymentDetails['PAYMENTREQUEST_0_QTY']          = 1;
        //$paymentDetails['PAYMENTREQUEST_0_NAME']         = $item->getName();
        $paymentDetails['PAYMENTREQUEST_0_DESC']         = substr(strip_tags($item->getDescription()), 0, 126);
        $paymentDetails['PAYMENTREQUEST_0_CUSTOM']       = $purchase->getId();

        // DIGITAL ITEM
        $paymentDetails['L_PAYMENTREQUEST_0_NAME0'] =  strip_tags($item->getName());
        $paymentDetails['L_PAYMENTREQUEST_0_AMT0'] =  number_format($item->getAmount(), 2);
        $paymentDetails['L_PAYMENTREQUEST_0_QTY0'] =  1;
        $paymentDetails['L_PAYMENTREQUEST_0_DESC0'] =  substr(strip_tags($item->getDescription()), 0, 126);
        $paymentDetails['L_PAYMENTREQUEST_0_TAXAMT0'] = number_format($tax, 2);
        $paymentDetails['L_PAYMENTREQUEST_0_ITEMCATEGORY0'] = Api::PAYMENTREQUEST_ITERMCATEGORY_PHYSICAL;

        $storage->updateModel($paymentDetails);

        $notifyToken = $this->getTokenFactory()->createNotifyToken($paymentName, $paymentDetails);
        $captureToken = $this->getTokenFactory()->createCaptureToken(
            $paymentName,
            $paymentDetails,
            'paypal_done'
        );

        $paymentDetails['INVNUM']    = $paymentDetails->getId();
        $paymentDetails['RETURNURL'] = $captureToken->getTargetUrl();
        $paymentDetails['CANCELURL'] = $captureToken->getTargetUrl();
        $paymentDetails['PAYMENTREQUEST_0_NOTIFYURL'] = $notifyToken->getTargetUrl();


        $storage->updateModel($paymentDetails);
        return $this->redirect($captureToken->getTargetUrl());
    }

    public function finishAction(Request $request)
    {
        if (!('POST' === $request->getMethod()))
        {
            throw new AccessDeniedException("You can not access this area");
        }

        # parameters needed
        $em           = $this->getDoctrine();
        $forUsername  = $request->request->get('_ign');
        $custom       = explode('|', $request->request->get('custom'));

        # get item
        $item = $em->getRepository('MaximCMSBundle:StoreItem')->findOneBy(array("id" => $custom[2]));

        # create a purchase
        $purchase = $this->get('purchase.helper')->createPurchase($this->getUser(), $item, $_SERVER["REMOTE_ADDR"], Purchase::PURCHASE_PENDING, $forUsername);

        # check payment method
        $paymentmethod = $request->request->get('button_shop_checkout');

        switch(strtoupper($paymentmethod))
        {
            case "PAYPAL":
                return $this->prepareAction($purchase);
            case "BTC":
                return $this->paymentCoinbase($purchase);
            default:
                return $this->prepareAction($purchase);
        }
    }

    public function paymentCoinbase($purchase)
    {
        $item = $purchase->getShop();

        $total = $item->getAmount() * 1;

        $tax = 0;
        if($item->getTax() > 0) {
            $tax = ($total * ($item->getTax() / 100));
        }

        # create a button response code
        $rest = $this->get('maxim_cms.rest.helper');

        $button = array(
            "button" => array(
                "name" => "test",
                "type" => "buy_now",
                "price_string" => "66.66",
                "price_currency_iso" => "USD",
                "custom" => "buy_now",
                "callback_url" => "http://www.example.com/my_custom_button_callback",
                "description" => "Sample description",
                "style" => "custom_large",
                "include_email" => true,
            )
        );

        $holder = $rest->execute(RESTHelper::METHOD_POST, array(), "https://coinbase.com/api/v1/buttons", $button)->getData();
        $l = $this->get('logger');
        $l->err(print_r($holder, true));
        print_r($holder);
        return new Response("test");
        //return $this->redirect("https://coinbase.com/checkouts/" . $holder['button']['code']);

    }

    public function completeAction(Request $request)
    {
        $logger = $this->get('logger');
        $token = $this->get('payum.security.http_request_verifier')->verify($request);
        $payment = $this->get('payum')->getPayment($token->getPaymentName());

        $status = new BinaryMaskStatusRequest($token);
        $payment->execute($status);

        if ($status->isSuccess())
        {
            $this->getRequest()->getSession()->getFlashBag()->set(
                'notice',
                'Payment success.'
            );
        }
        else if ($status->isPending())
        {
            $this->getRequest()->getSession()->getFlashBag()->set(
                'notice',
                'Payment is still pending. Credits were not added'
            );
        }
        else
        {
            $this->getRequest()->getSession()->getFlashBag()->set('error', 'Payment failed');
        }

        return $this->redirect($this->generateUrl("home"));
    }

	public function purchaseHistoryAction()
	{
		$em = $this->getDoctrine()->getManager();
        $qb = $em->createQueryBuilder();
		$user = $this->getUser();

		if($user)
		{
            $purchases = $qb->select('p')
                ->from('MaximCMSBundle:Purchase', 'p')
                ->innerJoin("MaximCMSBundle:User", "u", "WITH", "u.id = p.user")
                ->innerJoin("MaximCMSBundle:StoreItem", "s", "WITH", "p.storeItem = s.id")
                ->innerJoin('MaximCMSBundle:Website', 'w', 'WITH', 's.website = w.id')
                ->where($qb->expr()->notIn('p.status', array(Purchase::PURCHASE_FAILED, Purchase::PURCHASE_PENDING)))
                ->andWhere('w.id = :website')
                ->andWhere('u.id = :userid')
                ->setParameter('website', $this->container->getParameter('website'))
                ->setParameter(':userid', $user->getId())
                ->orderBy('p.date', 'DESC');

			$result['purchases'] = $purchases->getQuery()->getResult();
			return $this->render('MaximCMSBundle:Modules:account/purchase.html.twig', $result);
		}
	}

    /**
     * @return TokenFactory
     */
    protected function getTokenFactory()
    {
        return $this->get('payum.security.token_factory');

    }

    # future
    public function isBanned($username)
    {
        $config = Yaml::parse(__DIR__.'/../Resources/config/settings.yml');

        $conn = new \PDO('mysql:host='.$config['host'].';dbname='.$config['database'], $config['username'], $config['password']);

        try
        {
            $stmt = $conn->prepare("SELECT * FROM PlayerBans WHERE UPPER(Name) = :name");
            $stmt->execute(array(":name" => strtoupper($username)));

            if($stmt->rowCount() > 0)
            {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return (strtoupper($result['Perm']) == "TRUE" ? true : false);
            }
            else
            {
                return false;
            }
        }
        catch(\Exception $ex)
        {
            return array("success" => false, "message" => $ex->getMessage());
        }
    }
}