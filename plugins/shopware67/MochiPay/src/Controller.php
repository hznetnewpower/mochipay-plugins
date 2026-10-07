<?php declare(strict_types=1);
namespace MochiPay\Shopware;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
#[Route(defaults: ['_routeScope'=>['storefront']])]
class Controller extends \Shopware\Storefront\Controller\StorefrontController
{
    private $support;
    public function __construct(Support $support){$this->support=$support;}
    #[Route(path:'/mochipay/checkout',name:'frontend.mochipay.checkout',methods:['GET','POST'],defaults:['_noStore'=>true])]
    public function checkout(Request $request):Response
    {
        $input=array_merge($request->query->all(),$request->request->all());
        $row=$this->support->row((string)($input['id']??''));
        if(!$row)return new Response('Invalid saved payment.',404,['Cache-Control'=>'no-store']);
        list($status,$headers,$body)=\MochiPayShared\Page::handle($this->support->service($row['extra']['channel']),$input,$request->getMethod(),[$this->support,'settle']);
        return new Response($body,$status,$headers);
    }
}
