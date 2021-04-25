<?php

$installer = $this;
$installer->startSetup();
$sql = <<<SQLTEXT
CREATE TABLE IF NOT EXISTS `hom_barcodes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `meta_data` text NOT NULL,
  `file_name` varchar(100) NOT NULL,
  `status` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;
SQLTEXT;

$installer->run($sql);
$installer->endSetup();
