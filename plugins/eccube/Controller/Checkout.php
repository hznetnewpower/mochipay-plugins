<?php
namespace Plugin\MochiPay\Controller;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class Checkout extends \Eccube\Controller\AbstractController
{
    private $support,$cartService;
    public function __construct(\Plugin\MochiPay\Support $support,\Eccube\Service\CartService $cartService){$this->support=$support;$this->cartService=$cartService;}
    /** @Route("/mochipay/checkout", name="mochipay_checkout", methods={"GET","POST"}) */
    public function checkout(Request $request)
    {
        $service=$this->support->service();$input=array_merge($request->query->all(),$request->request->all());
        if(($input['action']??'')==='done'){
            $row=$service->authorized($input['id']??'',$input['token']??'');if(!$row['settled'])throw new \RuntimeException('Payment is not confirmed.');
            $request->getSession()->set(\Eccube\Service\OrderHelper::SESSION_ORDER_ID,(int)$row['local_id']);
            $order=$this->support->em->find(\Eccube\Entity\Order::class,(int)$row['local_id']);if(!$order||$this->getUser()!==$order->getCustomer())throw $this->createNotFoundException();
            if($this->cartService->getPreOrderId()===$order->getPreOrderId())$this->cartService->clear();return $this->redirectToRoute('shopping_complete');
        }
        list($code,$headers,$body)=\MochiPayShared\Page::handle($service,$input,$request->getMethod(),[$this->support,'settle']);return new Response($body,$code,$headers);
    }
}
