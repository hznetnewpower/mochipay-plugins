<?php
$fields=[];
$add=function($name,$title,$type,$default,$options=[])use(&$fields){$f=['name'=>$name,'title'=>$title,'type'=>$type,'default_value'=>$default,'default'=>$default,'channel_based'=>false,'locale_based'=>false];if($options)$f['options']=$options;$fields[]=$f;};
$add('title','Title','text','MochiPay');$add('description','Description','textarea','Cryptocurrency payments: ON_SITE and HPP');$add('active','Enable MochiPay','boolean',0);
$add('url','MochiPay URL','text','https://mochi.bz');$add('key','API key','text','');$add('secret','API secret','password','');
$add('mode','Payment mode','select','ON_SITE',[['title'=>'ON_SITE','value'=>'ON_SITE'],['title'=>'HPP','value'=>'HPP']]);$add('direction','Unique amount direction','select','UP',[['title'=>'UP','value'=>'UP'],['title'=>'DOWN','value'=>'DOWN']]);
foreach(\MochiPayShared\Payment::labels() as $code=>$label)$add('asset_'.$code,'Enable '.$label,'boolean',1);
$add('sort','Sort order','text',10);
return [['key'=>'sales.payment_methods.mochipay','name'=>'MochiPay','info'=>'Cryptocurrency payments: ON_SITE and HPP','sort'=>10,'fields'=>$fields]];
