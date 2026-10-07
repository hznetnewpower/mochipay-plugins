<?php
namespace MochiPay\Sylius\Command;
class Install extends \Symfony\Component\Console\Command\Command
{
 private $support;
 public function __construct(\MochiPay\Sylius\Support $support){parent::__construct('mochipay:install');$this->support=$support;}
 protected function configure():void{$this->setDescription('Create MochiPay payment storage without deleting existing records.');}
 protected function execute(\Symfony\Component\Console\Input\InputInterface $input,\Symfony\Component\Console\Output\OutputInterface $output):int{$this->support->store()->install();$output->writeln('MochiPay payment storage is ready.');return 0;}
}
