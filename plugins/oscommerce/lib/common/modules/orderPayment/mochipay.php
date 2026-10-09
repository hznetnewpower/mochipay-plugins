<?php
namespace common\modules\orderPayment;
use MochiPayShared\Payment;
use MochiPayShared\Store;
use common\helpers\OrderPayment;
class mochipay extends \common\classes\modules\ModulePayment
{
 public $code='mochipay',$title='MochiPay',$description='Cryptocurrency payments: ON_SITE and HPP',$enabled=false,$sort_order=0;
 protected $encrypted_keys=['MODULE_PAYMENT_MOCHIPAY_SECRET'];
 protected $defaultTranslationArray=['MODULE_PAYMENT_MOCHIPAY_TEXT_TITLE'=>'MochiPay','MODULE_PAYMENT_MOCHIPAY_TEXT_DESCRIPTION'=>'Cryptocurrency payments: ON_SITE and HPP'];
 public function __construct(){parent::__construct();require_once __DIR__.'/lib/MochiPay/bootstrap.php';$this->enabled=defined('MODULE_PAYMENT_MOCHIPAY_STATUS')&&MODULE_PAYMENT_MOCHIPAY_STATUS==='True';$this->sort_order=defined('MODULE_PAYMENT_MOCHIPAY_SORT_ORDER')?(int)MODULE_PAYMENT_MOCHIPAY_SORT_ORDER:0;}
 public static function getVersionHistory(){return ['1.0.6'=>'MochiPay ON_SITE and HPP payment gateway.'];}
 public static function getDescription(){return 'Cryptocurrency payments with verified ON_SITE and HPP checkout.';}
 public function configFor($platform){
  $c=Payment::defaults();foreach(['enabled'=>'STATUS','url'=>'URL','key'=>'KEY','secret'=>'SECRET','mode'=>'MODE','direction'=>'DIRECTION'] as $name=>$suffix){$v=$this->get_config_key((int)$platform,'MODULE_PAYMENT_MOCHIPAY_'.$suffix);if($v!==false)$c[$name]=$v;}
  $c['enabled']=$c['enabled']==='True';
  if($c['secret']!==''){$k=$this->getEncryptionKey()?:\Yii::$app->params['secKey.backend'];$c['secret']=\Yii::$app->security->decryptByKey(utf8_decode($c['secret']),$k);}
  $c['methods']=[];foreach(Payment::labels() as $code=>$label){$v=$this->get_config_key((int)$platform,'MODULE_PAYMENT_MOCHIPAY_ASSET_'.$code);if($v===false||$v==='True')$c['methods'][]=$code;}return $c;
 }
 public function store(){\Yii::$app->db->open();return Store::pdo(\Yii::$app->db->getMasterPdo(),\Yii::$app->db->tablePrefix.'mochipay_attempt');}
 public function service($platform){return new Payment($this->store(),$this->configFor($platform));}
 public function install($platform_id){$this->store()->install();return parent::install($platform_id);}
 public function selection(){return ['id'=>$this->code,'module'=>$this->title];}
 public function before_process(){if(parent::isPartlyPaid())throw new \RuntimeException('MochiPay supports whole-order payments. Partial invoice payments require a separate order.');$o=$this->manager->getOrderInstance();$this->service($o->info['platform_id']??$this->manager->getPlatformId());$o->info['order_status']=(int)DEFAULT_ORDERS_STATUS_ID;$o->isPaidUpdated=true;}
 public function amount($o){$currencies=\Yii::$container->get('currencies');$digits=(int)$currencies->currencies[$o->info['currency']]['decimal_places'];return number_format(round($o->info['total_inc_tax']*$o->info['currency_value'],$digits),$digits,'.','');}
 public function after_process(){
  $o=$this->manager->getOrderInstance();if(!$o->order_id||$o->info['payment_class']!=='mochipay')throw new \RuntimeException('Invalid checkout.');
  $service=$this->service($o->info['platform_id']);$id=(string)$o->order_id;
  $row=$service->store->locked('native:'.$id,function()use($o,$service,$id){return \Yii::$app->db->transaction(function()use($o,$service,$id){
   $ledger=OrderPayment::createDebitFromOrder($o,$o->info['total_inc_tax'],OrderPayment::OPYS_PENDING,['id'=>'mp-local-'.$id,'payment_class'=>'mochipay','payment_method'=>'MochiPay']);if(!$ledger)throw new \RuntimeException('Unable to save pending payment.');
   $endpoint=tep_href_link('callback/webhooks.payment.mochipay','','SSL');$return=tep_href_link(FILENAME_CHECKOUT_SUCCESS,'order_id='.$id,'SSL');
   return $service->prepare($id,$this->amount($o),$o->info['currency'],$return,$endpoint,'osCommerce',['platform'=>$o->info['platform_id'],'ledger_id'=>$ledger->orders_payment_id,'pending_status'=>(int)$o->info['order_status']]);
  });});
  $this->manager->set('mochipay_resume',Payment::url($row,'view'));
  $this->manager->clearAfterProcess();tep_redirect(Payment::url($row,'view'));
 }
 public function settle(array $row,array $remote){
  \Yii::$app->db->transaction(function()use($row,$remote){
   $o=$this->manager->getOrderInstanceWithId('\\common\\classes\\Order',(int)$row['local_id']);
   if(!$o||$o->info['payment_class']!=='mochipay'||(int)$o->info['platform_id']!==(int)$row['extra']['platform']||Payment::decimal($this->amount($o))!==$row['amount']||$o->info['currency']!==$row['currency'])throw new \RuntimeException('Store order changed.');
   $ledger=\common\models\OrdersPayment::findOne((int)$row['extra']['ledger_id']);
   if(!$ledger||$ledger->orders_payment_module!=='mochipay'||(int)$ledger->orders_payment_order_id!==(int)$o->order_id)throw new \RuntimeException('Payment ledger mismatch.');
   if((int)$ledger->orders_payment_status===OrderPayment::OPYS_SUCCESSFUL){if($ledger->orders_payment_transaction_id===$remote['order_id'])return;throw new \RuntimeException('Another payment is already recorded.');}
   if((int)$ledger->orders_payment_status!==OrderPayment::OPYS_PENDING||(int)$o->info['order_status']!==(int)$row['extra']['pending_status'])throw new \RuntimeException('Order requires review.');
   $ledger->orders_payment_transaction_id=$remote['order_id'];$ledger->orders_payment_transaction_status='PAID';$ledger->orders_payment_transaction_date=date('Y-m-d H:i:s');$ledger->orders_payment_status=OrderPayment::OPYS_SUCCESSFUL;if(!$ledger->save())throw new \RuntimeException('Unable to record payment.');
   $status=(int)$this->get_config_key($row['extra']['platform'],'MODULE_PAYMENT_MOCHIPAY_PAID_STATUS');if(!$status)$status=(int)DEFAULT_ORDERS_STATUS_ID;
   \common\helpers\Order::setStatus($o->order_id,$status,['comments'=>'MochiPay verified payment '.$remote['order_id'],'customer_notified'=>0]);$o->update_piad_information();$o->save_details();
  });
 }
 public function call_webhooks(){
  $input=array_merge(\Yii::$app->request->queryParams,\Yii::$app->request->bodyParams);$row=$this->store()->get($input['id']??'',$input['token']??'');if(!$row)throw new \yii\web\NotFoundHttpException('Payment was not found.');
  list($code,$headers,$body)=\MochiPayShared\Page::handle($this->service($row['extra']['platform']),$input,\Yii::$app->request->method,[$this,'settle']);
  $response=\Yii::$app->response;$response->format=\yii\web\Response::FORMAT_RAW;$response->statusCode=$code;foreach($headers as $k=>$v)$response->headers->set($k,$v);$response->content=$body;return $response;
 }
 public function describe_status_key(){return new \common\classes\modules\ModuleStatus('MODULE_PAYMENT_MOCHIPAY_STATUS','True','False');}
 public function describe_sort_key(){return new \common\classes\modules\ModuleSortOrder('MODULE_PAYMENT_MOCHIPAY_SORT_ORDER');}
 public function configure_keys(){
  $options=[];$add=function($key,$title,$value,$select=null)use(&$options){$r=['title'=>$title,'value'=>$value,'description'=>$title,'sort_order'=>(string)(count($options)+1)];if($select)$r['set_function']='tep_cfg_select_option(array('.implode(',',array_map(function($v){return "'".$v."'";},$select)).'), ';$options['MODULE_PAYMENT_MOCHIPAY_'.$key]=$r;};
  $add('STATUS','Enable MochiPay','False',['True','False']);$add('URL','MochiPay URL','https://mochi.bz');$add('KEY','API key','');$add('SECRET','API secret','');$add('MODE','Payment mode','ON_SITE',['ON_SITE','HPP']);$add('DIRECTION','Unique amount direction','UP',['UP','DOWN']);
  foreach(Payment::labels() as $code=>$label)$add('ASSET_'.$code,'Enable '.$label,'True',['True','False']);
  $add('PAID_STATUS','Paid order status (0: platform default)','0');$options['MODULE_PAYMENT_MOCHIPAY_PAID_STATUS']['set_function']='tep_cfg_pull_down_order_statuses(';$options['MODULE_PAYMENT_MOCHIPAY_PAID_STATUS']['use_function']='\\common\\helpers\\Order::get_order_status_name';$add('SORT_ORDER','Sort order','0');return $options;
 }
}
