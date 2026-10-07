<?php
namespace Drupal\mochipay\Controller;
use Drupal\commerce_payment\Entity\PaymentGatewayInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class Checkout
{
    public function handle(PaymentGatewayInterface $commerce_payment_gateway,Request $request){return self::respond($commerce_payment_gateway,$request);}
    public static function respond($gateway,Request $request)
    {
        $service=\Drupal\mochipay\Support::service($gateway);
        list($code,$headers,$body)=\MochiPayShared\Page::handle($service,array_merge($request->query->all(),$request->request->all()),$request->getMethod(),['Drupal\\mochipay\\Support','settle']);
        return new Response($body,$code,$headers);
    }
}
