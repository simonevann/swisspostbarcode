<?php

class HOM_Barcode_Model_LabelLayout {

    /**
     * Provide available options as a value/label array
     *
     * @return array
     */
    public function toOptionArray() {
        return array(
            array('value' => 'A5', 'label' => 'A5'),
            array('value' => 'A6', 'label' => 'A6'),
            array('value' => 'A7', 'label' => 'A7')
        );
    }

}
