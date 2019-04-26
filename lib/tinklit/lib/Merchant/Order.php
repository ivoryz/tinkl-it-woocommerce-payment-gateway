<?php
namespace Tinklit\Merchant;

use Tinklit\Tinklit;
use Tinklit\Merchant;
use Tinklit\OrderIsNotValid;
use Tinklit\OrderNotFound;

class Order extends Merchant
{
    private $order;

    public function __construct($order)
    {
        $this->order = $order;
    }

    public function toHash()
    {
        return $this->order;
    }

    public function __get($name)
    {
        return $this->order[$name];
    }

    public static function find($guid, $options = array(), $authentication = array())
    {
        try {
            return self::findOrFail($guid, $options, $authentication);
        } catch (OrderNotFound $e) {
            return false;
        }
    }

    public static function findOrFail($guid, $options = array(), $authentication = array())
    {
        $order = Tinklit::request('/invoices/' . $guid, 'GET', array(), $authentication);

        return new self($order);
    }

    public static function create($params, $options = array(), $authentication = array())
    {
        try {
            return self::createOrFail($params, $options, $authentication);
        } catch (OrderIsNotValid $e) {
            return false;
        }
    }

    public static function createOrFail($params, $options = array(), $authentication = array())
    {
        $order = Tinklit::request('/invoices', 'POST', $params, $authentication);

        return new self($order);
    }
}
