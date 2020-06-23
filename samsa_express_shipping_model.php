<?php
function getConsignment($order_id) {

	global $table_prefix, $wpdb;

	 $query = $wpdb->get_results("SELECT * FROM " . $table_prefix . "smsa WHERE order_id = '" . (int)$order_id . "' ORDER BY date_added DESC");

	return $query;
}