<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\PriceProduct\Business\Model;

use Generated\Shared\Transfer\PriceProductCriteriaTransfer;
use Generated\Shared\Transfer\PriceProductFilterTransfer;
use Spryker\Zed\PriceProduct\Business\Model\PriceType\PriceProductTypeReaderInterface;
use Spryker\Zed\PriceProduct\Business\Model\Product\PriceProductAbstractReaderInterface;
use Spryker\Zed\PriceProduct\Business\Model\Product\PriceProductConcreteReaderInterface;
use Spryker\Zed\PriceProduct\Dependency\Facade\PriceProductToPriceFacadeInterface;
use Spryker\Zed\PriceProduct\Dependency\Facade\PriceProductToProductFacadeInterface;
use Spryker\Zed\PriceProduct\Persistence\PriceProductQueryContainerInterface;

class Reader implements ReaderInterface
{
    const PRICE_TYPE_UNKNOWN = 'price type unknown: ';

    /**
     * @var string
     */
    protected static $netPriceModeIdentifier;

    /**
     * @var string
     */
    protected static $grossPriceModeIdentifier;

    /**
     * @var \Spryker\Zed\PriceProduct\Dependency\Facade\PriceProductToProductFacadeInterface
     */
    protected $productFacade;

    /**
     * @var \Spryker\Zed\PriceProduct\Dependency\Facade\PriceProductToPriceFacadeInterface
     */
    protected $priceFacade;

    /**
     * @var \Spryker\Zed\PriceProduct\Business\Model\PriceType\PriceProductTypeReaderInterface
     */
    protected $priceProductTypeReader;

    /**
     * @var \Spryker\Zed\PriceProduct\Business\Model\Product\PriceProductConcreteReaderInterface
     */
    protected $priceProductConcreteReader;

    /**
     * @var \Spryker\Zed\PriceProduct\Business\Model\Product\PriceProductAbstractReaderInterface
     */
    protected $priceProductAbstractReader;

    /**
     * @var \Spryker\Zed\PriceProduct\Business\Model\PriceProductCriteriaBuilderInterface
     */
    protected $priceProductCriteriaBuilder;

    /**
     * @param \Spryker\Zed\PriceProduct\Dependency\Facade\PriceProductToProductFacadeInterface $productFacade
     * @param \Spryker\Zed\PriceProduct\Dependency\Facade\PriceProductToPriceFacadeInterface $priceFacade
     * @param \Spryker\Zed\PriceProduct\Business\Model\PriceType\PriceProductTypeReaderInterface $priceProductTypeReader
     * @param \Spryker\Zed\PriceProduct\Business\Model\Product\PriceProductConcreteReaderInterface $priceProductConcreteReader
     * @param \Spryker\Zed\PriceProduct\Business\Model\Product\PriceProductAbstractReaderInterface $priceProductAbstractReader
     * @param \Spryker\Zed\PriceProduct\Business\Model\PriceProductCriteriaBuilderInterface $priceProductCriteriaBuilder
     */
    public function __construct(
        PriceProductToProductFacadeInterface $productFacade,
        PriceProductToPriceFacadeInterface $priceFacade,
        PriceProductTypeReaderInterface $priceProductTypeReader,
        PriceProductConcreteReaderInterface $priceProductConcreteReader,
        PriceProductAbstractReaderInterface $priceProductAbstractReader,
        PriceProductCriteriaBuilderInterface $priceProductCriteriaBuilder
    ) {
        $this->productFacade = $productFacade;
        $this->priceFacade = $priceFacade;
        $this->priceProductTypeReader = $priceProductTypeReader;
        $this->priceProductConcreteReader = $priceProductConcreteReader;
        $this->priceProductAbstractReader = $priceProductAbstractReader;
        $this->priceProductCriteriaBuilder = $priceProductCriteriaBuilder;
    }

    /**
     * @param string $sku
     * @param string|null $priceTypeName
     *
     * @return int
     */
    public function getPriceBySku($sku, $priceTypeName = null)
    {
        $priceProductCriteriaTransfer = $this->priceProductCriteriaBuilder->buildCriteriaWithDefaultValues($priceTypeName);

        return $this->findProductPrice($sku, $priceProductCriteriaTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\PriceProductFilterTransfer $priceProductFilterTransfer
     *
     * @return mixed
     */
    public function getPriceFor(PriceProductFilterTransfer $priceProductFilterTransfer)
    {
        $priceProductFilterTransfer->requireSku();

        $priceProductCriteriaTransfer = $this->priceProductCriteriaBuilder->buildCriteriaFromFilter($priceProductFilterTransfer);

        return $this->findProductPrice($priceProductFilterTransfer->getSku(), $priceProductCriteriaTransfer);
    }

    /**
     * @param int $idProductConcrete
     * @param int $idProductAbstract
     *
     * @return \Generated\Shared\Transfer\PriceProductTransfer[]
     */
    public function findProductConcretePrices($idProductConcrete, $idProductAbstract)
    {
        $abstractPriceProductTransfers = $this->priceProductAbstractReader->findProductAbstractPricesById($idProductAbstract);
        $concretePriceProductTransfers = $this->priceProductConcreteReader->findProductConcretePricesById($idProductConcrete);

        $priceProductTransfers = array_merge($abstractPriceProductTransfers, $concretePriceProductTransfers);

        return $priceProductTransfers;
    }

    /**
     * @param string $sku
     * @param string|null $priceTypeName
     *
     * @return bool
     */
    public function hasValidPrice($sku, $priceTypeName = null)
    {
        $priceTypeName = $this->priceProductTypeReader->handleDefaultPriceType($priceTypeName);

        if (!$this->priceProductTypeReader->hasPriceType($priceTypeName)) {
            return false;
        }

        $priceProductCriteriaTransfer = $this->priceProductCriteriaBuilder->buildCriteriaWithDefaultValues();

        return $this->isValidPrice($sku, $priceProductCriteriaTransfer);
    }

    /**
     * @param \Generated\Shared\Transfer\PriceProductFilterTransfer $priceProductFilterTransfer
     *
     * @return bool
     */
    public function hasValidPriceFor(PriceProductFilterTransfer $priceProductFilterTransfer)
    {
        $priceProductFilterTransfer->requireSku();

        $priceTypeName = $this->priceProductTypeReader->handleDefaultPriceType(
            $priceProductFilterTransfer->getPriceTypeName()
        );

        if (!$this->priceProductTypeReader->hasPriceType($priceTypeName)) {
            return false;
        }

        $priceProductCriteriaTransfer = $this->priceProductCriteriaBuilder->buildCriteriaFromFilter($priceProductFilterTransfer);

        return $this->isValidPrice($priceProductFilterTransfer->getSku(), $priceProductCriteriaTransfer);
    }

    /**
     * @param string $sku
     * @param string $priceTypeName
     * @param string $currencyIsoCode
     *
     * @return int
     */
    public function getProductPriceIdBySku($sku, $priceTypeName, $currencyIsoCode)
    {
        $priceProductCriteriaTransfer = $this->priceProductCriteriaBuilder->buildCriteriaWithDefaultValues();

        if ($this->priceProductConcreteReader->hasPriceForProductConcrete($sku, $priceProductCriteriaTransfer)) {
            return $this->priceProductConcreteReader->findPriceProductId($sku, $priceProductCriteriaTransfer);
        }

        if (!$this->priceProductAbstractReader->hasPriceForProductAbstract($sku, $priceProductCriteriaTransfer)) {
            $sku = $this->productFacade->getAbstractSkuFromProductConcrete($sku);
        }

        return $this->priceProductAbstractReader->findPriceProductId($sku, $priceProductCriteriaTransfer);
    }

    /**
     * @param string $sku
     *
     * @return array
     */
    public function findPricesBySkuGrouped($sku)
    {
        $priceProductTransfers = $this->findPricesBySku($sku);

        $prices = [];
        foreach ($priceProductTransfers as $priceProductTransfer) {
            $priceMoneyValueTransfer = $priceProductTransfer->getMoneyValue();

            $priceType = $priceProductTransfer->getPriceType()->getName();
            $currency = $priceMoneyValueTransfer->getCurrency()->getCode();

            if ($priceMoneyValueTransfer->getGrossAmount()) {
                $prices[$currency][$this->getGrossPriceModeIdentifier()][$priceType] = $priceMoneyValueTransfer->getGrossAmount();
            }

            if ($priceMoneyValueTransfer->getNetAmount()) {
                $prices[$currency][$this->getNetPriceModeIdentifier()][$priceType] = $priceMoneyValueTransfer->getNetAmount();
            }
        }

        return $prices;
    }

    /**
     * @param string $sku
     *
     * @return \Generated\Shared\Transfer\PriceProductTransfer[]
     */
    public function findPricesBySku($sku)
    {
        $abstractPriceProductTransfers = $this->priceProductAbstractReader->findProductAbstractPricesBySku($sku);
        $concretePriceProductTransfers = $this->priceProductConcreteReader->findProductConcretePricesBySku($sku);

        if (count($concretePriceProductTransfers) === 0) {
            return $abstractPriceProductTransfers;
        }

        return $this->mergeConcreteAndAbstractPrices($abstractPriceProductTransfers, $concretePriceProductTransfers);
    }

    /**
     * @param \Generated\Shared\Transfer\PriceProductTransfer[] $abstractPriceProductTransfers
     * @param \Generated\Shared\Transfer\PriceProductTransfer[] $concretePriceProductTransfers
     *
     * @return \Generated\Shared\Transfer\PriceProductTransfer[]
     */
    protected function mergeConcreteAndAbstractPrices(
        array $abstractPriceProductTransfers,
        array $concretePriceProductTransfers
    ) {
        $priceProductTransfers = [];
        foreach ($abstractPriceProductTransfers as $abstractKey => $priceProductAbstractTransfer) {
            foreach ($concretePriceProductTransfers as $concreteKey => $priceProductConcreteTransfer) {
                if ($abstractKey !== $concreteKey) {
                    continue;
                }

                $abstractMoneyValueTransfer = $priceProductAbstractTransfer->getMoneyValue();
                $concreteMoneyValueTransfer = $priceProductConcreteTransfer->getMoneyValue();
                if ($concreteMoneyValueTransfer->getGrossAmount() === null) {
                    $concreteMoneyValueTransfer->setGrossAmount($abstractMoneyValueTransfer->getGrossAmount());
                }

                if ($concreteMoneyValueTransfer->getNetAmount() === null) {
                    $concreteMoneyValueTransfer->setNetAmount($abstractMoneyValueTransfer->getNetAmount());
                }

                $priceProductTransfers[$concreteKey] = $priceProductConcreteTransfer;
            }

            if (!isset($priceProductTransfers[$abstractKey])) {
                $priceProductTransfers[$abstractKey] = $abstractPriceProductTransfers;
            }
        }
        return $priceProductTransfers;
    }

    /**
     * @param string $sku
     * @param \Generated\Shared\Transfer\PriceProductCriteriaTransfer $priceProductCriteriaTransfer
     *
     * @return int|null
     */
    protected function findProductPrice($sku, PriceProductCriteriaTransfer $priceProductCriteriaTransfer)
    {
        $priceProductConcrete = $this->priceProductConcreteReader->findPriceForProductConcrete($sku, $priceProductCriteriaTransfer);
        if ($priceProductConcrete !== null) {
            $concreteProductPrice = $this->findPriceByPriceMode($priceProductCriteriaTransfer, $priceProductConcrete);
            if ($concreteProductPrice !== null) {
                return (int)$concreteProductPrice;
            }
        }

        if ($this->productFacade->hasProductConcrete($sku)) {
            $sku = $this->productFacade->getAbstractSkuFromProductConcrete($sku);
        }

        $priceProductAbstract = $this->priceProductAbstractReader->findPriceForProductAbstract($sku, $priceProductCriteriaTransfer);
        if ($priceProductAbstract === null) {
            return null;
        }

        $abstractProductPrice = $this->findPriceByPriceMode($priceProductCriteriaTransfer, $priceProductAbstract);
        if ($abstractProductPrice !== null) {
            return (int)$abstractProductPrice;
        }

        return null;
    }

    /**
     * @param string $sku
     * @param \Generated\Shared\Transfer\PriceProductCriteriaTransfer $priceProductCriteriaTransfer
     *
     * @return bool
     */
    protected function isValidPrice($sku, PriceProductCriteriaTransfer $priceProductCriteriaTransfer)
    {
        if ($this->priceProductConcreteReader->hasPriceForProductConcrete($sku, $priceProductCriteriaTransfer)) {
            return true;
        }

        $abstractSku = $this->productFacade->getAbstractSkuFromProductConcrete($sku);
        if ($this->priceProductAbstractReader->hasPriceForProductAbstract($abstractSku, $priceProductCriteriaTransfer)) {
            return true;
        }

        return false;
    }

    /**
     * @return string
     */
    protected function getGrossPriceModeIdentifier()
    {
        if (!static::$grossPriceModeIdentifier) {
            static::$grossPriceModeIdentifier = $this->priceFacade->getGrossPriceModeIdentifier();
        }

        return static::$grossPriceModeIdentifier;
    }

    /**
     * @return string
     */
    protected function getNetPriceModeIdentifier()
    {
        if (!static::$netPriceModeIdentifier) {
            static::$netPriceModeIdentifier = $this->priceFacade->getNetPriceModeIdentifier();
        }

        return static::$netPriceModeIdentifier;
    }

    /**
     * @param \Generated\Shared\Transfer\PriceProductCriteriaTransfer $priceProductCriteriaTransfer
     * @param array $productPrice
     *
     * @return int
     */
    protected function findPriceByPriceMode(PriceProductCriteriaTransfer $priceProductCriteriaTransfer, array $productPrice)
    {
        if ($priceProductCriteriaTransfer->getPriceMode() === $this->getNetPriceModeIdentifier()) {
            return $productPrice[PriceProductQueryContainerInterface::COL_NET_PRICE];
        }

        return $productPrice[PriceProductQueryContainerInterface::COL_GROSS_PRICE];
    }
}
