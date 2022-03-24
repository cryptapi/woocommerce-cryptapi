<?php

namespace CryptAPI;

class Decimal {
    private $val = 0;
    private $precision = 18;

    private function to_int($float_val) {
        return $float_val * 10 ** $this->precision;
    }

    private function maybe_reduce_precision($new_val) {
        while ($new_val >= PHP_INT_MAX) {
            $new_val = ($new_val / 10 ** $this->precision) * 10 ** ($this->precision - 1);
            $this->precision -= 1;
        }

        $this->val = intval($new_val);
    }

    function __construct($float_val) {
        $this->sum($float_val);
    }

    function mult($float_val) {
        $new_val = $this->val * $this->to_int($float_val);
        $this->maybe_reduce_precision($new_val);
        return $this;
    }

    function div($float_val) {
        $new_val = $this->val / $this->to_int($float_val);
        $this->maybe_reduce_precision($new_val);
        return $this;
    }

    function sum($float_val) {
        $new_val = $this->val + $this->to_int($float_val);
        $this->maybe_reduce_precision($new_val);
        return $this;
    }

    function sub($float_val) {
        $new_val = $this->val - $this->to_int($float_val);
        $this->maybe_reduce_precision($new_val);
        return $this;
    }

    function result() {
        return $this->val / 10 ** $this->precision;
    }
}