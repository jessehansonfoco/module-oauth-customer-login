<?php

namespace JesseHanson\OAuthCustomerLogin\Helper;

class BasicCollection
{
    protected $items = [];

    public function addItem($key, $obj)
    {
        $this->items[$key] = $obj;
        return $this;
    }

    public function getItems()
    {
        return $this->items;
    }
}
