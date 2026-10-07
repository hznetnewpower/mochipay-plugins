<?php
$method=['code'=>'mochipay','title'=>'MochiPay','description'=>'Cryptocurrency payments: ON_SITE and HPP','class'=>\MochiPay\Bagisto\Payment\MochiPay::class,'active'=>false,'sort'=>10,'url'=>'https://mochi.bz','key'=>'','secret'=>'','mode'=>'ON_SITE','direction'=>'UP'];
foreach(\MochiPayShared\Payment::labels() as $code=>$label)$method['asset_'.$code]=true;
return ['mochipay'=>$method];
