<?php
namespace Plugin\MochiPay;
use Psr\Container\ContainerInterface;
class PluginManager extends \Eccube\Plugin\AbstractPluginManager
{
    public function enable(array $meta,ContainerInterface $container)
    {
        require_once __DIR__.'/lib/bootstrap.php';$em=$container->get('doctrine')->getManager();$db=$em->getConnection();
        \MochiPayShared\Store::doctrine($db,'mochipay_attempt')->install();
        $db->executeStatement('CREATE TABLE IF NOT EXISTS mochipay_config (id INTEGER NOT NULL PRIMARY KEY, config_text TEXT NOT NULL)');
        if(!$db->fetchOne('SELECT id FROM mochipay_config WHERE id=1'))$db->executeStatement('INSERT INTO mochipay_config (id,config_text) VALUES (1,?)',[\MochiPayShared\Payment::json(\MochiPayShared\Payment::defaults())]);
        $repo=$em->getRepository(\Eccube\Entity\Payment::class);
        if(!$repo->findOneBy(['method_class'=>Service\Method::class])){
            $last=$repo->findOneBy([],['sort_no'=>'DESC']);$p=new \Eccube\Entity\Payment();
            $p->setMethod('MochiPay');$p->setMethodClass(Service\Method::class);$p->setCharge(0);$p->setSortNo($last?$last->getSortNo()+1:1);$p->setVisible(true);$em->persist($p);$em->flush();
        }
    }
}
