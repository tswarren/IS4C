<?php
/*******************************************************************************

    Copyright 2013 Whole Foods Co-op

    This file is part of CORE-POS.

    CORE-POS is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    CORE-POS is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    in the file license.txt along with IT CORE; if not, write to the Free Software
    Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA

*********************************************************************************/

include(dirname(__FILE__) . '/../config.php');
if (!class_exists('FannieAPI')) {
    include_once(__DIR__ . '/../classlib2.0/FannieAPI.php');
}

class PurchasingSearchPage extends FannieRESTfulPage 
{
    protected $header = 'Purchase Orders';
    protected $title = 'Purchase Orders';

    public $description = '[Search Purchase Orders] finds orders/invoices containing a given item.';

    protected $must_authenticate = true;
    protected $enable_linea = true;

    public function get_id_view()
    {
        global $FANNIE_OP_DB;
        $dbc = FannieDB::get($FANNIE_OP_DB);

        $start = FormLib::get('date1');
        $end = FormLib::get('date2');
        $store = FormLib::get('store');
        $searchBy = FormLib::get('searchBy');

        $query = 'SELECT o.placedDate, o.orderID, o.vendorInvoiceID,
                v.vendorName, i.sku, i.internalUPC, i.description,
                i.brand, i.quantity
                FROM PurchaseOrderItems AS i
                    LEFT JOIN PurchaseOrder AS o ON i.orderID=o.orderID
                    LEFT JOIN vendors AS v ON o.vendorID=v.vendorID
                WHERE ';
        $args = array();
        switch ($searchBy) {
            case 'Invoice #':
                $query .= ' o.vendorInvoiceID=?';
                $args[] = $this->id;
                break;
            case 'SKU':
                $query .= ' i.sku LIKE ?';
                $args[] = '%' . $this->id;
                break;
            case 'UPC':
            default:
                $query .= ' i.internalUPC=?';
                $args[] = BarcodeLib::padUPC($this->id);
                break;
        }
        if ($start !== '' && $end !== '') {
            $query .= ' AND o.placedDate BETWEEN ? AND ? ';
        }
        if ($store) {
            $query .= ' AND o.storeID=? ';
        }
        $query .= 'ORDER BY o.placedDate DESC';

        if ($start !== '' && $end !== '') {
            $args[] = $start . ' 00:00:00';
            $args[] = $end . ' 23:59:59';
        }
        if ($store) {
            $args[] = $store;
        }

        $prep = $dbc->prepare($query);
        $res = $dbc->execute($prep, $args);

        $ret = '<p><a href="PurchasingSearchPage.php" class="btn btn-default">Back</a></p>
                <table class="table">';
        $ret .= '<tr><th>Date</th><th class="hidden-xs">Invoice</th><th>Vendor</th>
                <th class="hidden-xs">UPC</th><th class="hidden-xs">SKU</th>
                <th class="hidden-xs">Brand</th><th>Desc</th>
                <th class="hidden-xs">Qty</th></tr>';
        while($row = $dbc->fetch_row($res)) {
            $ret .= sprintf('<tr>
                            <td><a href="ViewPurchaseOrders.php?id=%d">%s</a></td>
                            <td class="hidden-xs"><a href="ViewPurchaseOrders.php?id=%d">%s</a></td>
                            <td>%s</td>
                            <td class="hidden-xs"><a href="../item/ItemEditorPage.php?searchupc=%s">%s</a></td>
                            <td class="hidden-xs">%s</td>
                            <td class="hidden-xs">%s</td>
                            <td>%s</td>
                            <td class="hidden-xs">%d</td>
                            </tr>',
                            $row['orderID'], date('Y-m-d', strtotime($row['placedDate'])),
                            $row['orderID'], $row['vendorInvoiceID'],
                            $row['vendorName'],
                            $row['internalUPC'], $row['internalUPC'],
                            $row['sku'],
                            $row['brand'],
                            $row['description'],
                            $row['quantity']
            );
        }
        $ret .= '</table>';

        return $ret;
    }

    public function get_view()
    {
        $stores = FormLib::storePicker();
        $ret = '<form class="form-horizontal" action="PurchasingSearchPage.php" method="get">';
        $ret .= '<div class="row">';
        $ret .= '<div class="col-sm-6">';

        $ret .= '<div class="form-group">';
        $ret .= '<div class="col-sm-3">
            <select name="searchBy" class="form-control input-sm">
            <option>UPC</option><option>SKU</option><option>Invoice #</option></select>
            </div>';
        $ret .= '<div class="col-sm-9"><input class="form-control" type="text" id="upcsku" name="id" /></div>';
        $ret .= '</div>';

        $ret .= '<div class="form-group">';
        $ret .= '<label class="col-sm-3 control-label">Store</label>';
        $ret .= '<div class="col-sm-9">' . $stores['html'] . '</div>';
        $ret .= '</div>';

        $ret .= '<div class="form-group">';
        $ret .= '<label for="date1" class="col-sm-3 control-label">Start Date</label>';
        $ret .= '<div class="col-sm-9"><input class="form-control date-field" type="text" id="date1" name="date1" /></div>';
        $ret .= '</div>';

        $ret .= '<div class="form-group">';
        $ret .= '<label for="date2" class="col-sm-3 control-label">End Date</label>';
        $ret .= '<div class="col-sm-9"><input class="form-control date-field" type="text" id="date2" name="date2" /></div>';
        $ret .= '</div>';

        $ret .= '</div>';

        $ret .= '<div class="col-sm-4 pull-left">';
        $ret .= FormLib::dateRangePicker();
        $ret .= '</div>';

        $ret .= '</div>';
        $ret .= '<p><button type="submit" class="btn btn-default">Search</button>';
        $ret .= ' Omit dates to search all orders
                (<a href="" onclick="$(\'#date1\').val(\'\');$(\'#date2\').val(\'\');return false;">Clear
                Dates</a>)</p>';
        $ret .= '</form>';

        $this->add_onload_command("\$('.form-control:first').focus();\n");
        $this->addOnloadCommand("enableLinea('#upcsku');");

        return $ret;
    }

    public function helpContent()
    {
        return '<p>Enter a UPC or SKU to find orders containing that
            item. Omit the dates to search all known orders.</p>';
    }

    public function unitTest($phpunit)
    {
        $phpunit->assertNotEquals(0, strlen($this->get_view()));
        $this->id = '4011';
        $phpunit->assertNotEquals(0, strlen($this->get_id_view()));
    }
}

FannieDispatch::conditionalExec();

