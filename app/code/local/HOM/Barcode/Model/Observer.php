<?php

class HOM_Barcode_Model_Observer
{
    public function addMassAction($observer)
    {
        $block = $observer->getEvent()->getBlock();
        if (get_class($block) == 'Mage_Adminhtml_Block_Widget_Grid_Massaction' && $block->getRequest()->getControllerName() == 'sales_order') {
            $block->addItem('hombarcodeprintship', array(
                'label' => 'Print Label and Ship Orders',
                'url' => Mage::app()->getStore()->getUrl('hombarcode/adminhtml_barcode/printandship')
            ));
            $block->addItem('hombarcodeprint', array(
                'label' => 'Print Label',
                'url' => Mage::app()->getStore()->getUrl('hombarcode/adminhtml_barcode/print')
            ));
        }
    }

    public function salesOrderShipmentSaveAfter(Varien_Event_Observer $observer)
    {
        try {
            $shipment = $observer->getEvent()->getShipment();
            $order = $shipment->getOrder();
            $barcode_helper = Mage::helper('barcode');
            // generate label
            $label_data = $barcode_helper->printOrderLabelOnly($order->getId());
            // set status & add tracking number
            if (isset($label_data['tracking_code'])) {
                Mage::getModel('sales/order_shipment_track')
                    ->setShipment($shipment)
                    ->setNumber($label_data['tracking_code'])
                    ->setCarrierCode('tracker1')
                    ->setTitle(Mage::getStoreConfig('customtrackers/tracker1/title', $order->getData('store_id')))
                    ->setOrderId($shipment->getData('order_id'))
                    ->save();
            }
            $order->setStatus('hom_shipped');
            $order->save();
            return $this;
        } catch (Exception $e) {
        }
    }
}
