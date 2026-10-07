<?php
namespace MochiPay\Sylius\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
class Checkout
{
 private $support;
 public function __construct(\MochiPay\Sylius\Support $support){$this->support=$support;}
 public function __invoke(Request $r):Response{
  $input=array_merge($r->query->all(),$r->request->all());$p=$this->support->payment($input['id']??'');
  list($code,$headers,$body)=\MochiPayShared\Page::handle($this->support->service($p),$input,$r->getMethod(),[$this->support,'settle']);return new Response($body,$code,$headers);
 }
}
