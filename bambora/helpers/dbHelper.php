<?php

class BamboraDBHelper
{
    /**
     * Create DB table for Payment Requests
     *
     * @return bool
     */
    public static function createBamboraPaymentRequestTable()
    {
        $table_name = _DB_PREFIX_ . 'bambora_payment_requests';

        $columns = [
            'payment_request_id' => 'varchar(255) NOT NULL',
            'id_order' => 'int(10) unsigned NOT NULL',
            'id_cart' => 'int(10) unsigned NOT NULL',
            'payment_request_url' => 'varchar(255) NOT NULL',
            'date_add' => 'datetime NOT NULL',
        ];

        $query = "CREATE TABLE IF NOT EXISTS `{$table_name}` (";

        foreach ($columns as $column_name => $options) {
            $query .= "`{$column_name}` {$options}, ";
        }

        $query .= ' PRIMARY KEY (`id_order`) )';

        return Db::getInstance()->Execute($query);
    }

    /**
     * Get paymentRequest  from the database with order id.
     *
     * @param string|int $id_order
     *
     * @return mixed
     */
    public static function getDbPaymentRequestByOrderId($id_order)
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bambora_payment_requests WHERE id_order = ' . pSQL(
            $id_order
        );

        return self::getDbPaymentRequests($query);
    }

    /**
     * Get paymentRequest  from the database with cart id.
     *
     * @param string|int $id_cart
     *
     * @return mixed
     */
    public static function getDbPaymentRequestByCartId($id_cart)
    {
        $query = 'SELECT * FROM ' . _DB_PREFIX_ . 'bambora_payment_requests WHERE id_cart = ' . pSQL(
            $id_cart
        );

        return self::getDbPaymentRequests($query);
    }

    /**
     * Get db paymentRequest for query.
     *
     * @param string $query
     *
     * @return mixed
     */
    public static function getDbPaymentRequests($query)
    {
        $paymentRequests = Db::getInstance()->executeS($query, true);

        if (!isset($paymentRequests)
            || empty($paymentRequests)
            || !isset($paymentRequests[0]['payment_request_id'])) {
            return null;
        }

        return $paymentRequests[0];
    }

    /**
     * Get db paymentRequests
     *
     * @param int $limit
     * @param int $page
     *
     * @return mixed
     *
     * @throws PrestaShopDatabaseException
     */
    public static function listPaymentRequests($limit = 20, $page = 1)
    {
        $offset = $limit * ($page - 1);

        $query = 'SELECT id_order, id_cart, payment_request_id, payment_request_url, date_add FROM ' . _DB_PREFIX_ . 'bambora_payment_requests LIMIT ' . pSQL(
            $limit
        ) . ' OFFSET ' . pSQL($offset);
        $paymentRequests = Db::getInstance()->executeS($query);

        if (!isset($paymentRequests) || count($paymentRequests) === 0) {
            return false;
        }

        return $paymentRequests;
    }

    /**
     * Get number of paymentRequests in db
     *
     * @return mixed
     *
     * @throws PrestaShopDatabaseException
     */
    public static function getNumberOfPaymentRequests()
    {
        $query = 'SELECT count(*)  FROM ' . _DB_PREFIX_ . 'bambora_payment_requests';
        $row_count = Db::getInstance()->getValue($query);

        if (!isset($row_count)) {
            return 0;
        }

        return $row_count;
    }

    /**
     * Add the transaction to the database.
     *
     * @param mixed $id_order
     * @param mixed $id_cart
     * @param mixed $payment_request_id
     * @param mixed $url
     *
     * @return bool
     */
    public static function addDbPaymentRequest(
        $id_order,
        $id_cart,
        $payment_request_id,
        $url,
    ) {
        $query = 'INSERT INTO ' . _DB_PREFIX_ . 'bambora_payment_requests
                (id_order, id_cart, payment_request_id, payment_request_url, date_add)
                VALUES
                (' . pSQL($id_order) . ', ' . pSQL($id_cart) . ', \'' . pSQL(
            $payment_request_id
        ) . '\',  \'' . pSQL($url) . '\', NOW() )';

        return self::executePaymentRequestDbQuery($query);
    }

    /**
     * Delete a payment request from db
     *
     * @param mixed $payment_request_id
     *
     * @return bool
     */
    public static function deleteDbPaymentRequest($payment_request_id)
    {
        if (!$payment_request_id) {
            return false;
        }

        $query = 'DELETE FROM ' . _DB_PREFIX_ . 'bambora_payment_requests WHERE payment_request_id="' . pSQL(
            $payment_request_id
        ) . '"';

        return self::executePaymentRequestDbQuery($query);
    }

    /**
     * Execute database query.
     *
     * @param mixed $query
     *
     * @return bool
     */
    public static function executePaymentRequestDbQuery($query)
    {
        try {
            return Db::getInstance()->Execute($query);
        } catch (Exception $e) {
            return false;
        }
    }
}
