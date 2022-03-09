<?php

/**
 * D2dSoft
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL v3.0) that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL: https://d2d-soft.com/license/AFL.txt
 *
 * DISCLAIMER
 * Do not edit or add to this file if you wish to upgrade this extension/plugin/module to newer version in the future.
 *
 * @author     D2dSoft Developers <developer@d2d-soft.com>
 * @copyright  Copyright (c) 2021 D2dSoft (https://d2d-soft.com)
 * @license    https://d2d-soft.com/license/AFL.txt
 */

class D2DSoft_DataMigration_Model_Product
    extends Mage_Catalog_Model_Product
{
    /**
     * Add image to media gallery
     *
     * @param string        $file              file path of image in file system
     * @param string|array  $mediaAttribute    code of attribute with type 'media_image',
     *                                          leave blank if image should be only in gallery
     * @param boolean       $move              if true, it will move source file
     * @param boolean       $exclude           mark image as disabled in product page view
     * @param string        $label
     * @return Mage_Catalog_Model_Product
     */
    public function addImageToMediaGallery($file, $mediaAttribute = null, $move = false, $exclude = true, $label = '')
    {
        $attributes = $this->getTypeInstance(true)->getSetAttributes($this);
        if (!isset($attributes['media_gallery'])) {
            return $this;
        }
        $mediaGalleryAttribute = $attributes['media_gallery'];
        /* @var $mediaGalleryAttribute Mage_Catalog_Model_Resource_Eav_Attribute */
        /* @var $backend D2DSoft_DataMigration_Model_Product_Attribute_Backend_Media */
        $backend = Mage::getModel('dsdm/product_attribute_backend_media');
        $backend->setAttribute($mediaGalleryAttribute);
        $backend->addImage($this, $file, $mediaAttribute, $move, $exclude, $label);
        return $this;
    }

}