<?php

require_once(Mage::getBaseDir('lib') . '/PDFMerger/PDFMerger.php');

class HOM_Barcode_Adminhtml_BarcodeController extends Mage_Adminhtml_Controller_Action {

    public function printandshipAction() {
        $fileNames = array();
        $barcode_helper = Mage::helper('barcode');

        $order_ids = $this->getRequest()->getPost('order_ids', array());

        foreach ($order_ids as $order_id) {
            // generate label
            $label_data = $barcode_helper->printOrderLabelOnly($order_id);
            if (isset($label_data['tracking_code'])) {
                $fileNames[] = $label_data['tracking_code'] . '.pdf';
            }

            // if (isset($label_data['tracking_code'])) {
            //     $barcode_helper->download($label_data['tracking_code'] . '.pdf');
            // }
        }
        $file_path = Mage::getBaseDir('media') . '/barcodes/';
        $pdf = new PDFMerger;
        //$barcodeFilename = array();
        if(count($fileNames)) {
            foreach($fileNames as $fileName) {
                if(file_exists($file_path . $fileName)) {
                    //$barcodeFilename[] = str_replace('.pdf', '', $fileName);
                    $pdf->addPDF($file_path . $fileName, 'all');
                }
            }
            //$barcodeFilenameFinal = implode('_', $barcodeFilename) . '.pdf';
            $barcodeFilenameFinal = 'barcodes_' . time() . '.pdf';
            $pdf->merge('file', $file_path . $barcodeFilenameFinal);
            $barcode_helper->download($barcodeFilenameFinal);
            //$zip_file_name = 'barcodes_' . time() . '.zip';
            //$file_path = Mage::getBaseDir('media') . '/barcodes/';
            //$this->zipFilesDownload2($fileNames,$zip_file_name,$file_path);
        }

        foreach ($order_ids as $order_id) {
            // ship order
            $barcode_helper->shipOrder($order_id);
        }

        // redirect
        Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/'))->sendResponse();
    }

    public function printAction() {
        $fileNames = array();
        $barcode_helper = Mage::helper('barcode');

        $order_ids = $this->getRequest()->getPost('order_ids', array());
        foreach ($order_ids as $order_id) {
            // generate label
            $label_data = $barcode_helper->printOrderLabelOnly($order_id);

            if (isset($label_data['tracking_code'])) {
                $fileNames[] = $label_data['tracking_code'] . '.pdf';
            }

            // if (isset($label_data['tracking_code'])) {
            //     $barcode_helper->download($label_data['tracking_code'] . '.pdf');
            // }
        }
        $file_path = Mage::getBaseDir('media') . '/barcodes/';
        $pdf = new PDFMerger;
        //$barcodeFilename = array();
        if(count($fileNames)) {
            foreach($fileNames as $fileName) {
                if(file_exists($file_path . $fileName)) {
                    //$barcodeFilename[] = str_replace('.pdf', '', $fileName);
                    $pdf->addPDF($file_path . $fileName, 'all');
                }
            }
            //$barcodeFilenameFinal = implode('_', $barcodeFilename) . '.pdf';
            $barcodeFilenameFinal = 'barcodes_' . time() . '.pdf';
            $pdf->merge('file', $file_path . $barcodeFilenameFinal);
            $barcode_helper->download($barcodeFilenameFinal);
            //$zip_file_name = 'barcodes_' . time() . '.zip';
            //$file_path = Mage::getBaseDir('media') . '/barcodes/';
            //$this->zipFilesDownload($fileNames,$zip_file_name,$file_path);
        }

        // redirect
        Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/'))->sendResponse();
    }

    public function zipFilesDownload($file_names,$archive_file_name,$file_path)
    {
        $zip = new ZipArchive();

        if ($zip->open($archive_file_name, ZIPARCHIVE::CREATE )!==TRUE) {
            return false;
        }

        foreach($file_names as $files)
        {
            $zip->addFile($file_path.$files,$files);
        }
        $zip->close();

        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$archive_file_name");
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$archive_file_name");
        exit;
    }

    public function zipFilesDownload2($file_names,$archive_file_name,$file_path)
    {
        $zip = new ZipArchive();

        if ($zip->open($archive_file_name, ZIPARCHIVE::CREATE )!==TRUE) {
            return false;
        }

        foreach($file_names as $files)
        {
            $zip->addFile($file_path.$files,$files);
        }
        $zip->close();

        header("Content-type: application/zip");
        header("Content-Disposition: attachment; filename=$archive_file_name");
        header("Pragma: no-cache");
        header("Expires: 0");
        readfile("$archive_file_name");
    }

}
