<?php

declare(strict_types=1);

namespace PayPal\CommercePlatform\Model\Config\Source;

/**
 * Get button style options
 */
class ButtonOptions
{

    /**
     * Button layout source getter
     *
     * @return array
     */
    public function getLayout(): array
    {
        return [
            'vertical'   => __('Vertical'),
            'horizontal' => __('Horizontal')
        ];
    }

    /**
     * Button color source getter
     *
     * @return array
     */
    public function getColor(): array
    {
        return [
            'gold'   => __('Gold'),
            'blue'   => __('Blue'),
            'silver' => __('Silver'),
            'white'  => __('White'),
            'black'  => __('Black')
        ];
    }

    /**
     * Button shape source getter
     *
     * @return array
     */
    public function getShape(): array
    {
        return [
            'pill' => __('Pill'),
            'rect' => __('Rectangle')
        ];
    }

    /**
     * Button size source getter
     *
     * @return array
     */
/*
    public function getSize(): array
    {
        return [
            'vertical' => __('Vertical'),
            'horizontal' => __('Horizontal')
        ];
    }
 */

    /**
     * Button label source getter
     *
     * @return array
     */
    public function getLabel(): array
    {
        return [
            'checkout'    => __('Checkout'),
            'pay'         => __('Pay'),
            'buynow'      => __('Buy Now'),
            'paypal'      => __('PayPal'),
            'installment' => __('Installment'),
        ];
    }

    /**
     * Button Tagline source getter
     *
     * @return array
     */
    public function getTagline(): array
    {
        return [
            'true'  => __('True'),
            'false' => __('False')
        ];
    }

    /**
     * Brazil button installment period source getter
     *
     * @return array
     */
    public function getBrInstallmentPeriod(): array
    {
        $numbers = range(2, 12);

        return array_combine($numbers, $numbers);
    }

    /**
     * Mexico button installment period source getter
     *
     * @return array
     */
    public function getMxInstallmentPeriod(): array
    {
        $numbers = range(3, 12, 3);

        return array_combine($numbers, $numbers);
    }
}
