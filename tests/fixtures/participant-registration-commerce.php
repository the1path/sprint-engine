<?php
/** Actual WooCommerce/HPOS setup and inspection, never a commerce emulator. */
if('yes'!==getenv('SE_TEST_DISPOSABLE')||!getenv('SE_TEST_WP_ROOT'))exit(2);
$_SERVER['HTTPS']='on';require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if(!defined('SE0313_DISPOSABLE')||!is_file(ABSPATH.'SE0313-DISPOSABLE'))exit(3);
require_once ABSPATH.'wp-admin/includes/plugin.php';wp_set_current_user(1);$command=$argv[1]??'inspect';
if('activate'===$command){$r=activate_plugin('woocommerce/woocommerce.php');if(is_wp_error($r))throw new RuntimeException('Actual Woo activation failed');echo "Actual Woo activated.\n";exit;}
if(!function_exists('WC'))throw new RuntimeException('Actual Woo must load');
if('setup'===$command){
 WC_Install::create_pages();$checkout=(int)get_option('woocommerce_checkout_page_id');wp_update_post(array('ID'=>$checkout,'post_content'=>'[woocommerce_checkout]'));
 update_option('woocommerce_currency','GBP');update_option('woocommerce_default_country','GB');update_option('woocommerce_calc_taxes','no');update_option('woocommerce_enable_guest_checkout','yes');update_option('woocommerce_custom_orders_table_enabled','yes');
 update_option('woocommerce_cod_settings',array('enabled'=>'yes','title'=>'Synthetic offline payment','enable_for_virtual'=>'yes','enable_for_methods'=>array()));
 update_option('woocommerce_allow_tracking','no');delete_transient('_wc_activation_redirect');update_option('woocommerce_enable_myaccount_registration','yes');update_option('woocommerce_registration_generate_username','yes');update_option('woocommerce_registration_generate_password','yes');
 $product=new WC_Product_Simple();$product->set_name('Synthetic virtual item');$product->set_status('publish');$product->set_virtual(true);$product->set_regular_price('1.00');$product->set_price('1.00');$product->set_tax_status('none');$product_id=$product->save();
 $email='commerce-'.bin2hex(random_bytes(10)).'@example.invalid';$guest=wc_create_order(array('customer_id'=>0,'status'=>'pending'));$guest->set_billing_email($email);$guest->save();
 $customer=wc_create_new_customer('preserved-customer-'.bin2hex(random_bytes(8)).'@example.invalid','','Synthetic-Customer-Password31!');if(is_wp_error($customer))throw new RuntimeException('Customer fixture refused');
 update_option('se0313_commerce',array('product'=>$product_id,'email'=>$email,'guest_order'=>$guest->get_id(),'existing_customer'=>$customer,'checkout'=>get_permalink($checkout),'myaccount'=>wc_get_page_permalink('myaccount')));
}
$f=get_option('se0313_commerce');$user=get_user_by('email',$f['email']);$guest=wc_get_order($f['guest_order']);$orders=$user?wc_get_orders(array('customer_id'=>$user->ID,'limit'=>10,'return'=>'objects')):array();
$results=array();foreach($orders as $order)$results[]=array('same_user'=>$order->get_customer_id()===$user->ID,'status'=>$order->get_status(),'created_via'=>$order->get_created_via());
echo wp_json_encode(array('fixture'=>$f,'woo'=>WC_VERSION,'hpos'=>Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),'new_user'=>$user?$user->ID:0,'new_roles'=>$user?$user->roles:array(),'historic_guest_still_unlinked'=>0===$guest->get_customer_id(),'existing_customer_roles'=>get_userdata($f['existing_customer'])->roles,'orders'=>$results));
