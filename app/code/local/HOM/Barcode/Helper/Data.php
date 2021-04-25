<?php

class HOM_Barcode_Helper_Data extends Mage_Core_Helper_Abstract {

    function soapConnect() {
        // SOAP Configuration
        $username = Mage::getStoreConfig('hombarcode/soap_connection/username', Mage::app()->getStore());
        $password = Mage::getStoreConfig('hombarcode/soap_connection/password', Mage::app()->getStore());
        $endpoint_url = Mage::getStoreConfig('hombarcode/soap_connection/endpoint_url', Mage::app()->getStore());

        $controller_direction = Mage::getModuleDir('controllers', 'HOM_Barcode');
        if (strpos($controller_direction, '\\') !== false) {
            $SOAP_wsdl_file_path = $controller_direction . '\Adminhtml\barcode_v2_2.wsdl';
        } else {
            $SOAP_wsdl_file_path = $controller_direction . '/Adminhtml/barcode_v2_2.wsdl';
        }

        $SOAP_config = array(
            // Webservice Endpoint URL
            'location' => $endpoint_url,
            'login' => $username,
            'password' => $password,
                // Encoding for Strings
                // 'encoding' => 'ISO-8859-1',
                // Optional Proxy Config
                // (if you are behind a proxy):
                // 'proxy_host' => 'proxy.mydomain.com',
                // 'proxy_port' => 8080,
                // Optional Proxy Authentication
                // (if your proxy needs a username and password):
                // 'proxy_login' => 'proxy-username',
                // 'proxy_password' => 'proxy-password',
                // Addtional debug trace information:
                // 'trace' => true,
                // Connection timeout (in seconds):
                // 'connection_timeout' => 90
        );

        // SOAP Client Initialization
        try {
            $SOAP_Client = new SoapClient($SOAP_wsdl_file_path, $SOAP_config);
            return $SOAP_Client;
        } catch (SoapFault $fault) {
            $error_message = 'Error in SOAP Initialization';
            $redirect_url = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/');
            $this->homRedirect('error', $error_message, $redirect_url);
            //echo('Error in SOAP Initialization: ' . $fault->__toString());
            //exit;
        }
    }

    /**
     * A simple helper-Method for getting multiple elements (=objects or values) from SOAP Responses as an array
     * and also treating a single element as if it was an array.
     * Passing null results in an empty array.
     * @param $root the element data (=either a single object or value or an array of mutliple values or null)
     * @return an array containing the passed element(s) (or an empty array if the passed value was null)
     */
    function getElements($root) {
        if ($root == null) {
            return array();
        }
        if (is_array($root)) {
            return $root;
        } else {
            // simply wrap a single value or object into an array
            return array($root);
        }
    }

    /**
     * A simple helper method to transform an array of strings to a concatenated string with comma separation.
     * @param $strings an array containing string elements
     * @return a string with concatenated string elements separated by comma
     */
    function toCommaSeparatedString($strings) {
        $res = "";
        $delimiter = "";
        foreach ($strings as $str) {
            $res .= $delimiter . $str;
            $delimiter = ", ";
        }
        return $res;
    }

    function homRedirect($type = '', $message = '', $url = '') {
        if ($message) {
            if ($type == 'error') {
                Mage::getSingleton('core/session')->addError($this->__($message));
            } else {
                Mage::getSingleton('core/session')->addSuccess($this->__($message));
            }
        }
        if ($url) {
            Mage::app()->getResponse()->setRedirect($url)->sendResponse();
        } else {
            Mage::app()->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/'))->sendResponse();
        }
    }

    function generateSingleBarcodes($generateSingleBarcodesRequest) {
        $SOAP_Client = $this->soapConnect();

        // 2. Web service call
        $response = null;
        try {
            $response = $SOAP_Client->GenerateSingleBarcodes($generateSingleBarcodesRequest);
        } catch (SoapFault $fault) {

            echo('Error in GenerateSingleBarcodes: ' . $fault->__toString() . '<br />');

//            $error_message = 'Error in Generate Single Barcodes';
//            $redirect_url = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/');
//            $this->homRedirect('error', $error_message, $redirect_url);
        }

        // 3. Process requests: save label images and check for errors
        // (see documentation of structure in "Handbuch Webservice Barcode", section 4.3.2)
        foreach ($this->getElements($response->Envelope->Data->Provider->Sending->Item) as $item) {
            if ($item->Errors != null) {

                // Error in Single  Request Item:
                // This barcode label was not generated due to errors.
                // The received error messages are returned in the specified language of the request.
                // This means, that the label was not generated,
                // but other labels from other request items in same call
                // might have been generated successfully anyway.
                $errorMessages = "";
                $delimiter = "";
                foreach ($this->getElements($item->Errors->Error) as $error) {
                    $errorMessages .= $delimiter . $error->Message;
                    $delimiter = ",";
                }

                $error_message = 'ERROR for item with itemID = ' . $item->ItemID . ": " . $errorMessages;
                $redirect_url = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/');
                $this->homRedirect('error', $error_message, $redirect_url);
            } else {
                // Get successfully generated label as binary data:
                $itemID = $item->IdentID;
                $identCode = $item->IdentCode;

                $counter = 1;
                $basePath = 'outputfolder/testOutput_GenerateSingleBarcodes_' . $identCode . '_';
                foreach ($this->getElements($item->Barcodes->Barcode) as $barcode) {
                    // Save the binary image data to image file:
                    $filename = '' . $basePath . '' . $counter . '.gif';
                    file_put_contents($filename, $barcode);
                    $counter++;
                }
                $numberOfItems = $counter - 1;

                // Printout some label information (and warnings, if any):
                echo '<p>' . $numberOfItems . 'Barcodes successfully generated for identCode=' . $identCode . ': <br/>';
                if ($item->Warnings != null) {
                    $warningMessages = "";
                    foreach ($this->getElements($item->Warnings->Warning) as $warning) {
                        $warningMessages .= $warning->Message . ",";
                    }
                    echo 'with WARNINGS: ' . $warningMessages . '.<br/>';
                }
                echo 'All files start with: <br/><img src="' . $basePath . '"/><br/>';
                echo '</p>';
            }
        }
    }

    function generateBarcode($generateBarcodeRequest) {
        $SOAP_Client = $this->soapConnect();

        // 2. Web service call
        $response = null;
        try {
            $response = $SOAP_Client->GenerateBarcode($generateBarcodeRequest);
        } catch (SoapFault $fault) {
            //echo('Error in GenerateBarcode: ' . $fault->__toString() . '<br />');
            $error_message = 'Error in Generate Barcodes';
            $redirect_url = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/');
            $this->homRedirect('error', $error_message, $redirect_url);
        }

        // 3. Process requests: save label images and check for errors
        // (see documentation of structure in "Barcode web service manual")
        foreach ($this->getElements($response->Data) as $data) {
            if ($data->Errors != null) {
                // Error in Barcode Request Item:
                // This barcode label was not generated due to errors.
                // The received error messages are returned in the specified language of the request .
                // This means, that the label was not generated,
                // but other labels from other request items in same call
                // might have been generated successfully anyway.
                $errorMessages = "";
                $delimiter = "";
                foreach ($this->getElements($data->Errors->Error) as $error) {
                    $errorMessages .= $delimiter . $error->Message;
                    $delimiter = ",";
                }
                $error_message = 'ERROR for request: ' . $errorMessages;
                $redirect_url = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order/');
                $this->homRedirect('error', $error_message, $redirect_url);
            } else {
                // Get successfully generated barcide as binary data:
                $barcodeBinaryData = $data->Barcode;
                $deliveryNoteRef = $data->DeliveryNoteRef;
                $barcodeDefinition = $data->BarcodeDefinition;
                $barcodeType = $barcodeDefinition->BarcodeType;
                $imageFileType = $barcodeDefinition->ImageFileType;
                $imageResolution = $barcodeDefinition->ImageResolution;
                // Save the binary image data to image file:

                $media_direction = Mage::getBaseDir('media');
                if (strpos($media_direction, '\\') !== false) {
                    $output_folder = Mage::getBaseDir('media') . '\\barcodes\\';
                } else {
                    $output_folder = Mage::getBaseDir('media') . '/barcodes/';
                }

                $filename = $output_folder . $deliveryNoteRef . '.gif';
                file_put_contents($filename, $barcodeBinaryData);
                // Printout some label information (and warnings, if any):
                echo '<p>Label generated successfully for Delivery Note Reference =' . $deliveryNoteRef . ': <br/>';
                if ($data->Warnings != null) {
                    $warningMessages = "";
                    foreach ($this->getElements($data->Warnings->Warning) as $warning) {
                        $warningMessages .= $warning->Message . ",";
                    }
                    echo 'with WARNINGS: ' . $warningMessages . '.<br/>';
                }
                echo $filename . ':<br/><img src="' . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'barcodes/' . $deliveryNoteRef . '.gif' . '"/><br/>';
                echo '</p>';
            }
        }
    }

    function generateLabel($generateLabelRequest, $order_id) {

        $order = Mage::getModel('sales/order')->load($order_id);

        $label_data = array();

        if (count($this->getBarcodeByOrderId($order_id))) {
            $existed_barcode = $this->getBarcodeByOrderId($order_id);
            if (count($existed_barcode)) {
                $existed_barcode_meta_data = json_decode($existed_barcode[0]['meta_data']);
                if (isset($existed_barcode_meta_data->tracking_code)) {
                    $label_data['tracking_code'] = $existed_barcode_meta_data->tracking_code;
                }
            }
            Mage::getSingleton('core/session')->addSuccess($this->__('Label has been generated for order #') . $order->getIncrementId());
            return $label_data;
        }

        $SOAP_Client = $this->soapConnect();
        $response = null;
        try {
            $response = $SOAP_Client->GenerateLabel($generateLabelRequest);
        } catch (SoapFault $fault) {
            Mage::getSingleton('core/session')->addError($this->__('Can not generate label for order #') . $order->getIncrementId());
        }

        foreach ($this->getElements($response->Envelope->Data->Provider->Sending->Item) as $item) {
            if ($item->Errors != null) {
                $errorMessages = "";
                $delimiter = "";
                foreach ($this->getElements($item->Errors->Error) as $error) {
                    $errorMessages .= $delimiter . $error->Message;
                    $delimiter = ",";
                }
                Mage::getSingleton('core/session')->addError($this->__('Can not generate label for order #') . $order->getIncrementId());
            } else {
                $identCode = $item->IdentCode;
                $labelBinaryData = $item->Label;

                $label_data['tracking_code'] = $identCode;

                $media_direction = Mage::getBaseDir('media');
                if (strpos($media_direction, '\\') !== false) {
                    $output_folder = Mage::getBaseDir('media') . '\\barcodes\\';
                } else {
                    $output_folder = Mage::getBaseDir('media') . '/barcodes/';
                }

                $filename = $output_folder . $identCode . '.pdf';
                file_put_contents($filename, $labelBinaryData);

                echo '<p>Label generated successfully for identCode=' . $identCode . ': <br/>';
                if ($item->Warnings != null) {
                    $warningMessages = "";
                    foreach ($this->getElements($item->Warnings->Warning) as $warning) {
                        $warningMessages .= $warning->Message . ",";
                    }
                    echo 'with WARNINGS: ' . $warningMessages . '.<br/>';
                }
                echo $filename . ':<br/><img src="' . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'barcodes/' . $identCode . '.pdf"/><br/>';
                echo '</p>';

                $barcode_meta_data = array(
                    'tracking_code' => $identCode
                );
                $barcode_data = array(
                    'order_id' => $order_id,
                    'meta_data' => json_encode($barcode_meta_data),
                    'file_name' => $identCode . '.pdf',
                    'status' => 1
                );
                $this->newBarcode($barcode_data);

                Mage::getSingleton('core/session')->addSuccess($this->__('Label has been generated for order #') . $order->getIncrementId());
            }
        }

        return $label_data;
    }

    function generateLabelOnly($generateLabelRequest, $order_id) {

        $order = Mage::getModel('sales/order')->load($order_id);

        $label_data = array();

        if (count($this->getBarcodeByOrderId($order_id))) {
            $existed_barcode = $this->getBarcodeByOrderId($order_id);
            if (count($existed_barcode)) {
                $existed_barcode_meta_data = json_decode($existed_barcode[0]['meta_data']);
                if (isset($existed_barcode_meta_data->tracking_code)) {
                    $label_data['tracking_code'] = $existed_barcode_meta_data->tracking_code;
                }
            }
            return $label_data;
        }

        $SOAP_Client = $this->soapConnect();
        $response = null;
        try {
            $response = $SOAP_Client->GenerateLabel($generateLabelRequest);
        } catch (SoapFault $fault) {
            Mage::getSingleton('core/session')->addError($this->__('Can not generate label for order #') . $order->getIncrementId());
        }

        foreach ($this->getElements($response->Envelope->Data->Provider->Sending->Item) as $item) {
            if ($item->Errors != null) {
                $errorMessages = "";
                $delimiter = "";
                foreach ($this->getElements($item->Errors->Error) as $error) {
                    $errorMessages .= $delimiter . $error->Message;
                    $delimiter = ",";
                }
                Mage::getSingleton('core/session')->addError($this->__('Can not generate label for order #') . $order->getIncrementId());
            } else {
                $identCode = $item->IdentCode;
                $labelBinaryData = $item->Label;

                $label_data['tracking_code'] = $identCode;

                $media_direction = Mage::getBaseDir('media');
                if (strpos($media_direction, '\\') !== false) {
                    $output_folder = Mage::getBaseDir('media') . '\\barcodes\\';
                } else {
                    $output_folder = Mage::getBaseDir('media') . '/barcodes/';
                }

                $filename = $output_folder . $identCode . '.pdf';
                file_put_contents($filename, $labelBinaryData);

                echo '<p>Label generated successfully for identCode=' . $identCode . ': <br/>';
                if ($item->Warnings != null) {
                    $warningMessages = "";
                    foreach ($this->getElements($item->Warnings->Warning) as $warning) {
                        $warningMessages .= $warning->Message . ",";
                    }
                    echo 'with WARNINGS: ' . $warningMessages . '.<br/>';
                }
                echo $filename . ':<br/><img src="' . Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'barcodes/' . $identCode . '.pdf"/><br/>';
                echo '</p>';

                $barcode_meta_data = array(
                    'tracking_code' => $identCode
                );
                $barcode_data = array(
                    'order_id' => $order_id,
                    'meta_data' => json_encode($barcode_meta_data),
                    'file_name' => $identCode . '.pdf',
                    'status' => 1
                );
                $this->newBarcode($barcode_data);
            }
        }

        return $label_data;
    }

    function newBarcode($barcode_data) {
        $write = Mage::getSingleton("core/resource")->getConnection("core_write");
        $query = "insert into hom_barcodes "
                . "(order_id, meta_data, file_name, status) values "
                . "(:order_id, :meta_data, :file_name, :status)";
        $write->query($query, $barcode_data);
    }

    function getBarcodeByOrderId($order_id) {
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $results = $read->fetchAll("select * from hom_barcodes where order_id = " . $order_id);
        return $results;
    }

    function printOrderLabel($order_id) {
        $frankinglicense = Mage::getStoreConfig('hombarcode/webservice/franking_license', Mage::app()->getStore());
        $label_layout = Mage::getStoreConfig('hombarcode/webservice/label_layout', Mage::app()->getStore());
        $fi_customer_name1 = Mage::getStoreConfig('hombarcode/file_infos/name1', Mage::app()->getStore());
        $fi_customer_street = Mage::getStoreConfig('hombarcode/file_infos/street', Mage::app()->getStore());
        $fi_customer_zip = Mage::getStoreConfig('hombarcode/file_infos/zip', Mage::app()->getStore());
        $fi_customer_city = Mage::getStoreConfig('hombarcode/file_infos/city', Mage::app()->getStore());
        $fi_customer_domicile_post_office = Mage::getStoreConfig('hombarcode/file_infos/domicile_post_office', Mage::app()->getStore());

        $order = Mage::getModel('sales/order')->load($order_id);
        $order_data = $order->getData();
        $order_address_data = $order->getShippingAddress()->getData();

        $data_request = array(
            'ItemID' => $order_id,
            'Recipient_Name1' => $order_data['customer_firstname'] . ' ' . $order_data['customer_lastname'],
            'Recipient_Street' => $order_address_data['street'],
            'Recipient_ZIP' => $order_address_data['postcode'],
            'Recipient_City' => $order_address_data['city'],
            //'Recipient_Phone' => $order_address_data['telephone'],
            'Recipient_Email' => $order_data['customer_email'],
        );

        $data_request['basic_service_code'] = 'ECO';
        if (strpos($order_data['shipping_description'], 'PostPac Priority') !== false) {
            $data_request['basic_service_code'] = 'PRI';
        }

        $generateLabelRequest = array(
            'Language' => 'en',
            'Envelope' => array(
                'LabelDefinition' => array(
                    'LabelLayout' => $label_layout,
                    'PrintAddresses' => 'RecipientAndCustomer',
                    'ImageFileType' => 'PDF',
                    'ImageResolution' => 300,
                    'PrintPreview' => false
                ),
                'FileInfos' => array(
                    'FrankingLicense' => $frankinglicense,
                    'PpFranking' => false,
                    'Customer' => array(
                        'Name1' => $fi_customer_name1,
                        // 'Name2' => 'Generalagentur',
                        'Street' => $fi_customer_street,
                        // 'POBox' => 'Postfach 600',
                        'ZIP' => $fi_customer_zip,
                        'City' => $fi_customer_city,
                        // 'Country' => 'CH',
                        // 'Logo' => $logo_binary_data,
                        // 'LogoFormat' => 'GIF',
                        'DomicilePostOffice' => $fi_customer_domicile_post_office
                    ),
                    'CustomerSystem' => 'PHP Client System'
                ),
                'Data' => array(
                    'Provider' => array(
                        'Sending' => array(
                            //'SendingID' => 'auftragsreferenz',
                            'Item' => array(
                                array(// 1.Item ...
                                    'ItemID' => $data_request['ItemID'],
                                    // 'ItemNumber' => '12345678',
                                    // 'IdentCode' => '1234',
                                    'Recipient' => array(
                                        //'PostIdent' => 'IdentCodeUser',
                                        //'Title' => 'Frau',
                                        //'Vorname' => 'Melanie',
                                        'Name1' => $data_request['Recipient_Name1'],
                                        //'Name2' => 'Müller AG',
                                        'Street' => $data_request['Recipient_Street'],
                                        //'HouseNo' => '21',
                                        //'FloorNo' => '1',
                                        //'MailboxNo' => '1111',
                                        'ZIP' => $data_request['Recipient_ZIP'],
                                        'City' => $data_request['Recipient_City'],
                                        //'Country' => 'CH',
                                        //'Phone' => $data_request['Recipient_Phone'], // für ZAW3213
                                        //'Mobile' => '0793381111',
                                        'EMail' => $data_request['Recipient_Email']
                                    //'LabelAddress' => array(
                                    //	'LabelLine' => array('LabelLine 1',
                                    //						 'LabelLine 2',
                                    //						 'LabelLine 3',
                                    //						 'LabelLine 4',
                                    //						 'LabelLine 5'
                                    //						)
                                    //)
                                    ),
                                    //'AdditionalINFOS' => array(
                                    // Cash-On-Delivery amount in CHF for service 'BLN':
                                    //	'AdditionalData' => array(
                                    //  	'Type' => 'NN_BETRAG',
                                    //		'Value' => '12.5'
                                    //	),
                                    // Cash-On-Delivery example for 'BLN' with ESR:
                                    //	AdditionalData' => array(
                                    //	'Type' => 'NN_ESR_REFNR',
                                    //	'Value' => '965993000000000000001237460'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_ESR_KNDNR',
                                    //	'Value' => '010003757'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_EMAIL',
                                    //	'Value' => 'hans.muster@mail.ch'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_MOBILE',
                                    //	'Value' => '0791234567'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_PHONE',
                                    //	'Value' => '0311234567'
                                    //	),
                                    // Cash-On-Delivery example for 'BLN' with IBAN:
                                    //	AdditionalData' => array(
                                    //	'Type' => 'NN_IBAN',
                                    //	'Value' => 'CH10002300A1023502601'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_NAME_VORNAME',
                                    //	'Value' => 'Hans Muster'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_STRASSE',
                                    //	'Value' => 'Musterstrasse 11'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_PLZ',
                                    //	'Value' => '3011'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_ORT',
                                    //	'Value' => 'Bern'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_EMAIL',
                                    //	'Value' => 'hans.muster@mail.ch'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_MOBILE',
                                    //	'Value' => '0791234567'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_PHONE',
                                    //	'Value' => '0311234567'
                                    //	),
                                    //),
                                    'Attributes' => array(
                                        'PRZL' => array(
                                            // At least one code is required (schema validation)
                                            // Basic service code(s) (optional, default="ECO"):
                                            $data_request['basic_service_code'],
                                            // Additional service codes (optional)
                                            //'N',
                                            //'FRA',
                                            // Delivery instruction codes (optional)
                                            //'ZAW3211',
                                            //'ZAW3213'
                                        ),
                                        // Cash on delivery amount in CHF for service 'N':
                                        //'Amount' => 12.5,
                                        //'FreeText' => 'Freitext',
                                        // 'DeliveryDate' => '2010-06-19',
                                        // 'ParcelNo' => 2,
                                        // 'ParcelTotal' => 5,
                                        // 'DeliveryPlace' => 'Vor der Haustüre',
                                        'ProClima' => true
                                    )
                                //'Notification' => array(
                                //	// Notification structure ...
                                //)
                                )
                            //,
                            // Add addtional items here for multiple requests in one web service call ...
                            // array( // 2.Item ...
                            //		... // same structure as above
                            //	),
                            )
                        )
                    )
                )
            )
        );

        return $label_data;
    }

    function printOrderLabelOnly($order_id) {
        $frankinglicense = Mage::getStoreConfig('hombarcode/webservice/franking_license', Mage::app()->getStore());
        $label_layout = Mage::getStoreConfig('hombarcode/webservice/label_layout', Mage::app()->getStore());
        $fi_customer_name1 = Mage::getStoreConfig('hombarcode/file_infos/name1', Mage::app()->getStore());
        $fi_customer_street = Mage::getStoreConfig('hombarcode/file_infos/street', Mage::app()->getStore());
        $fi_customer_zip = Mage::getStoreConfig('hombarcode/file_infos/zip', Mage::app()->getStore());
        $fi_customer_city = Mage::getStoreConfig('hombarcode/file_infos/city', Mage::app()->getStore());
        $fi_customer_domicile_post_office = Mage::getStoreConfig('hombarcode/file_infos/domicile_post_office', Mage::app()->getStore());

        $order = Mage::getModel('sales/order')->load($order_id);
        $order_data = $order->getData();
        $order_address_data = $order->getShippingAddress()->getData();

        $data_request = array(
            'ItemID' => $order_id,
            'Recipient_Name1' => $order_data['customer_firstname'] . ' ' . $order_data['customer_lastname'],
            'Recipient_Street' => $order_address_data['street'],
            'Recipient_ZIP' => $order_address_data['postcode'],
            'Recipient_City' => $order_address_data['city'],
            //'Recipient_Phone' => $order_address_data['telephone'],
            'Recipient_Email' => $order_data['customer_email'],
        );

        $data_request['basic_service_code'] = 'ECO';
        if (strpos($order_data['shipping_description'], 'PostPac Priority') !== false) {
            $data_request['basic_service_code'] = 'PRI';
        }

        $generateLabelRequest = array(
            'Language' => 'en',
            'Envelope' => array(
                'LabelDefinition' => array(
                    'LabelLayout' => $label_layout,
                    'PrintAddresses' => 'RecipientAndCustomer',
                    'ImageFileType' => 'PDF',
                    'ImageResolution' => 300,
                    'PrintPreview' => false
                ),
                'FileInfos' => array(
                    'FrankingLicense' => $frankinglicense,
                    'PpFranking' => false,
                    'Customer' => array(
                        'Name1' => $fi_customer_name1,
                        // 'Name2' => 'Generalagentur',
                        'Street' => $fi_customer_street,
                        // 'POBox' => 'Postfach 600',
                        'ZIP' => $fi_customer_zip,
                        'City' => $fi_customer_city,
                        // 'Country' => 'CH',
                        // 'Logo' => $logo_binary_data,
                        // 'LogoFormat' => 'GIF',
                        'DomicilePostOffice' => $fi_customer_domicile_post_office
                    ),
                    'CustomerSystem' => 'PHP Client System'
                ),
                'Data' => array(
                    'Provider' => array(
                        'Sending' => array(
                            //'SendingID' => 'auftragsreferenz',
                            'Item' => array(
                                array(// 1.Item ...
                                    'ItemID' => $data_request['ItemID'],
                                    // 'ItemNumber' => '12345678',
                                    // 'IdentCode' => '1234',
                                    'Recipient' => array(
                                        //'PostIdent' => 'IdentCodeUser',
                                        //'Title' => 'Frau',
                                        //'Vorname' => 'Melanie',
                                        'Name1' => $data_request['Recipient_Name1'],
                                        //'Name2' => 'Müller AG',
                                        'Street' => $data_request['Recipient_Street'],
                                        //'HouseNo' => '21',
                                        //'FloorNo' => '1',
                                        //'MailboxNo' => '1111',
                                        'ZIP' => $data_request['Recipient_ZIP'],
                                        'City' => $data_request['Recipient_City'],
                                        //'Country' => 'CH',
                                        //'Phone' => $data_request['Recipient_Phone'], // für ZAW3213
                                        //'Mobile' => '0793381111',
                                        'EMail' => $data_request['Recipient_Email']
                                    //'LabelAddress' => array(
                                    //	'LabelLine' => array('LabelLine 1',
                                    //						 'LabelLine 2',
                                    //						 'LabelLine 3',
                                    //						 'LabelLine 4',
                                    //						 'LabelLine 5'
                                    //						)
                                    //)
                                    ),
                                    //'AdditionalINFOS' => array(
                                    // Cash-On-Delivery amount in CHF for service 'BLN':
                                    //	'AdditionalData' => array(
                                    //  	'Type' => 'NN_BETRAG',
                                    //		'Value' => '12.5'
                                    //	),
                                    // Cash-On-Delivery example for 'BLN' with ESR:
                                    //	AdditionalData' => array(
                                    //	'Type' => 'NN_ESR_REFNR',
                                    //	'Value' => '965993000000000000001237460'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_ESR_KNDNR',
                                    //	'Value' => '010003757'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_EMAIL',
                                    //	'Value' => 'hans.muster@mail.ch'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_MOBILE',
                                    //	'Value' => '0791234567'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_PHONE',
                                    //	'Value' => '0311234567'
                                    //	),
                                    // Cash-On-Delivery example for 'BLN' with IBAN:
                                    //	AdditionalData' => array(
                                    //	'Type' => 'NN_IBAN',
                                    //	'Value' => 'CH10002300A1023502601'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_NAME_VORNAME',
                                    //	'Value' => 'Hans Muster'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_STRASSE',
                                    //	'Value' => 'Musterstrasse 11'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_PLZ',
                                    //	'Value' => '3011'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_END_ORT',
                                    //	'Value' => 'Bern'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_EMAIL',
                                    //	'Value' => 'hans.muster@mail.ch'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_MOBILE',
                                    //	'Value' => '0791234567'
                                    //	),
                                    //  AdditionalData' => array(
                                    //	'Type' => 'NN_CUS_PHONE',
                                    //	'Value' => '0311234567'
                                    //	),
                                    //),
                                    'Attributes' => array(
					// MODIFICATO DA VANNUCCI
                                        'PRZL' => array(
                                            // At least one code is required (schema validation)
                                            // Basic service code(s) (optional, default="ECO"):
                                            $data_request['basic_service_code'],
                                            // Additional service codes (optional)
                                            //'N',
                                            //'FRA',
                                            // Delivery instruction codes (optional)
                                            //'ZAW3211',
                                            //'ZAW3213'
                                        ),
					// FINE MODIFICA DA VANNUCCI
                                        // Cash on delivery amount in CHF for service 'N':
                                        //'Amount' => 12.5,
                                        //'FreeText' => 'Freitext',
                                        // 'DeliveryDate' => '2010-06-19',
                                        // 'ParcelNo' => 2,
                                        // 'ParcelTotal' => 5,
                                        // 'DeliveryPlace' => 'Vor der Haustüre',
                                        'ProClima' => false
                                    )
                                //'Notification' => array(
                                //	// Notification structure ...
                                //)
                                )
                            //,
                            // Add addtional items here for multiple requests in one web service call ...
                            // array( // 2.Item ...
                            //		... // same structure as above
                            //	),
                            )
                        )
                    )
                )
            )
        );

        $label_data = $this->generateLabelOnly($generateLabelRequest, $order_id);
        return $label_data;
    }

    function download($filename) {
        if (!empty($filename)) {
            // Specify file path.

            $media_direction = Mage::getBaseDir('media');
            if (strpos($media_direction, '\\') !== false) {
                $output_folder = Mage::getBaseDir('media') . '\\barcodes\\';
            } else {
                $output_folder = Mage::getBaseDir('media') . '/barcodes/';
            }

            $path = $output_folder; // '/uplods/'
            $download_file = $path . $filename;

            // Check file is exists on given path.
            if (file_exists($download_file)) {
                // Getting file extension.
                $extension = explode('.', $filename);
                $extension = $extension[count($extension) - 1];
                // For Gecko browsers
                //header('Content-Transfer-Encoding: binary');
                //header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($path)) . ' GMT');
                // Supports for download resume
                //header('Accept-Ranges: bytes');
                // Calculate File size
                //header('Content-Length: ' . filesize($download_file));
                //header('Content-Encoding: none');
                // Change the mime type if the file is not PDF
                header("Content-type: application/pdf");
                // Make the browser display the Save As dialog
                header("Content-Disposition: attachment; filename='".$filename."'");
                readfile($download_file);
            } else {
                echo 'File does not exists on given path';
            }
        }
    }

    function shipOrder($order_id) {

        $order = Mage::getModel('sales/order')->load($order_id);

        try {
            $email = true;
            $trackingNum = '';
            $carrier = 'custom';
            $includeComment = false;
            $comment = "Order Shipped";

            $convertor = Mage::getModel('sales/convert_order');
            $shipment = $convertor->toShipment($order);

            foreach ($order->getAllItems() as $orderItem) {

                if (!$orderItem->getQtyToShip()) {
                    continue;
                }
                if ($orderItem->getIsVirtual()) {
                    continue;
                }
                $item = $convertor->itemToShipmentItem($orderItem);
                $qty = $orderItem->getQtyToShip();
                $item->setQty($qty);
                $shipment->addItem($item);
            }

            $carrierTitle = NULL;

            if ($carrier == 'custom') {
                $carrierTitle = 'Swiss Post';
            }
            $data = array();
            $data['carrier_code'] = $carrier;
            $data['title'] = $carrierTitle;
            $data['number'] = $trackingNum;

            $track = Mage::getModel('sales/order_shipment_track')->addData($data);
            //$shipment->addTrack($track);

            $shipment->register();
            $shipment->addComment($comment, $email && $includeComment);
            $shipment->setEmailSent(true);
            $shipment->getOrder()->setIsInProcess(true);

            $transactionSave = Mage::getModel('core/resource_transaction')
                    ->addObject($shipment)
                    ->addObject($shipment->getOrder())
                    ->save();

            $shipment->sendEmail($email, ($includeComment ? $comment : ''));

            //$order->setState('hom_shipped', true)->save();

            $order->setData('state', "hom_shipped");
            $order->setStatus("hom_shipped");
            $order->save();

            $shipment->save();

            Mage::getSingleton('core/session')->addSuccess($this->__('Order #') . $order->getIncrementId() . ' has been shipped');
        } catch (Mage_Core_Exception $e) {
            $error_message = 'Order #' . $order->getIncrementId() . ' can not ship or already be shipped';
            Mage::getSingleton('core/session')->addError($error_message);
        }
    }

    function readServiceGroups($serviceGroupsRequest) {
        $SOAP_Client = $this->soapConnect();

        try {
            $serviceGroupsResponse = $SOAP_Client->ReadServiceGroups($serviceGroupsRequest);
        } catch (SoapFault $fault) {
            echo('Error in ReadServiceGroups: ' . $fault->__toString() . '<br />');
            exit;
        }

        echo "<h1>Read Services Test Execution</h1>";
        echo "<br/>";
        echo "<br/>";

        // 2. For each service group: read and display service codes and label layouts
        foreach ($this->getElements($serviceGroupsResponse->ServiceGroup) as $group) {
            echo "<b>Available service codes and layouts for Service-Group '" . $group->Description . "'</b>";
            echo "<br/>";
            echo "<br/>";

            //
            // 2.1. Basic services for service group
            $basicServicesRequest = array('Language' => 'de', 'ServiceGroupID' => $group->ServiceGroupID);
            try {
                $basicServicesResponse = $SOAP_Client->ReadBasicServices($basicServicesRequest);
            } catch (SoapFault $fault) {
                echo('Error in ReadBasicServices: ' . $fault->__toString() . '<br />');
                exit;
            }
            echo "Basic Services:";
            echo "<ul>";
            foreach ($this->getElements($basicServicesResponse->BasicService) as $basicService) {
                echo "<li>";
                $basicServiceCodes = $this->getElements($basicService->PRZL);
                echo $this->toCommaSeparatedString($basicServiceCodes);

                //
                // 2.1.1. Additional services for basic service
                $additionalServicesRequest = array('Language' => 'de', 'PRZL' => $basicServiceCodes);
                try {
                    $additionalServicesResponse = $SOAP_Client->ReadAdditionalServices($additionalServicesRequest);
                } catch (SoapFault $fault) {
                    echo('Error in ReadAdditonalServices: ' . $fault->__toString() . '<br />');
                    exit;
                }
                echo "<ul>";
                echo "<li>Additional service codes: ";
                $delimiter = "";
                foreach ($this->getElements($additionalServicesResponse->AdditionalService) as $additionalService) {
                    echo $delimiter . $additionalService->PRZL;
                    $delimiter = ",";
                }
                echo "</li>";
                echo "</ul>";
                // 2.1.1.
                //

		//
		// 2.1.2. Delivery instructions for basic service
                $deliveryInstructionsRequest = array('Language' => 'de', 'PRZL' => $basicServiceCodes);
                try {
                    $deliveryInstructionsResponse = $SOAP_Client->ReadDeliveryInstructions($deliveryInstructionsRequest);
                } catch (SoapFault $fault) {
                    echo('Error in ReadDeliveryInstructions: ' . $fault->__toString() . '<br />');
                    exit;
                }

                echo "<ul>";
                echo "<li>Delivery instruction codes: ";
                $delimiter = "";
                foreach ($this->getElements($deliveryInstructionsResponse->DeliveryInstructions) as $deliveryInstruction) {
                    echo $delimiter . $deliveryInstruction->PRZL;
                    $delimiter = ",";
                }
                echo "</li>";
                echo "</ul>";
                // -- 2.1.2.
                //

		//
		// 2.1.3. Label layouts for basic service
                $labelLayoutsRequest = array('Language' => 'de', 'PRZL' => $basicServiceCodes);
                try {
                    $labelLayoutsResponse = $SOAP_Client->ReadLabelLayouts($labelLayoutsRequest);
                } catch (SoapFault $fault) {
                    echo('Error in ReadLabelLayouts: ' . $fault->__toString() . '<br />');
                    exit;
                }

                echo "<ul>";
                echo "<li>Label Layouts: ";
                // elements
                echo "<ul>";
                foreach ($this->getElements($labelLayoutsResponse->LabelLayout) as $labelLayout) {
                    echo "<li>";
                    echo $labelLayout->LabelLayout . ": ";
                    echo "max. " . $labelLayout->MaxServices . " services ";
                    echo "and " . $labelLayout->MaxDeliveryInstructions . " delivery instructions, ";
                    if ($labelLayout->FreeTextAllowed) {
                        echo "freetext allowed";
                    } else {
                        echo "freetext not allowed";
                    }
                    echo "</li>";
                }
                echo "</ul>";
                // -- elements
                echo "</li>";
                echo "</ul>";
                // -- 2.1.3.
                //

		echo "</li>";
            }
            echo "</ul>";
        }

        echo "<br/>";
        echo "<br/>";
        echo "<br/>";
        echo "<br/>";
        echo "<h1>Allowed Services By Franking License 60108764</h1>";

//
// 3. Read allowed services by franking license
        $allowedServicesRequest = array('FrankingLicense' => '60108764', 'Language' => 'en');
        try {
            $allowedServicesResponse = $SOAP_Client->ReadAllowedServicesByFrankingLicense($allowedServicesRequest);
        } catch (SoapFault $fault) {
            echo('Error in ReadAllowedServicesByFrankingLicense: ' . $fault->__toString() . '<br />');
            exit;
        }

        echo "<ul>";
        foreach ($this->getElements($allowedServicesResponse->ServiceGroups) as $serviceGroup) {
            echo "<li>";
            echo "ServiceGroup: " . $serviceGroup->ServiceGroup->ServiceGroupID . ", " . $serviceGroup->ServiceGroup->Description . "";
            echo "<ul>";
            foreach ($this->getElements($serviceGroup->BasicService) as $basicService) {
                echo "<li>";
                $przls = count($basicService->PRZL);
                if ($przls > 1) {
                    echo "BasicService: " . $basicService->Description . ": " . $this->toCommaSeparatedString($basicService->PRZL) . "";
                } else {
                    echo "BasicService: " . $basicService->Description . ": " . $basicService->PRZL . "";
                }
                echo "</li>";
            }
            echo "</ul>";
            echo "</li>";
            echo "<br/>";
        }
        echo "</ul>";
    }

    function validateCombination($validationRequest) {
        $SOAP_Client = $this->soapConnect();

        // 2. Web service call
        try {
            $response = $SOAP_Client->ValidateCombination($validationRequest);
        } catch (SoapFault $fault) {
            echo('Error in ValidateCombination: ' . $fault->__toString() . '<br />');
        }

// 3. Process response
// (see documentation of structure in "Handbuch Webservice Barcode", section 4.2.2)
        foreach ($this->getElements($response->Envelope->Data->Provider->Sending->Item) as $item) {
            if ($item->Errors != null) {

                // Errors in validation ...
                // (error messages are returned in the requested language)
                $errorMessages = "";
                foreach ($this->getElements($item->Errors->Error) as $error) {
                    $errorMessages .= $error->Message . ',';
                }
                echo '<p>Validation-ERROR for item with itemID=' . $item->ItemID . ": " . $errorMessages . '.<br/></p>';
            } else {
                // Successful validation
                echo '<p>Validation was successfull for service code combination in item with itemID=' . $item->ItemID . '.<br/></p>';

                // Also display warnings
                if ($item->Warnings != null) {
                    $warningMessages = "";
                    foreach ($this->getElements($item->Warnings->Warning) as $warning) {
                        $warningMessages .= $warning->Message . ",";
                    }
                    echo 'with WARNINGS: ' . $warningMessages . '.<br/>';
                }
            }
        }
    }

}
