<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

function cloudinary_upload(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if (($file['size'] ?? 0) > 6*1024*1024) throw new RuntimeException('Images must be 6 MB or smaller.');
    $cloud=env_value('CLOUDINARY_CLOUD_NAME');$key=env_value('CLOUDINARY_API_KEY');$secret=env_value('CLOUDINARY_API_SECRET');
    if(!$cloud||!$key||!$secret) throw new RuntimeException('Cloudinary is not configured. Add credentials to the server .env file.');
    $timestamp=time();$folder=env_value('CLOUDINARY_FOLDER','ecom-minutes');$signature=sha1('folder='.$folder.'&timestamp='.$timestamp.$secret);
    $payload=['file'=>new CURLFile($file['tmp_name']), 'api_key'=>$key,'timestamp'=>$timestamp,'folder'=>$folder,'signature'=>$signature];
    $handle=curl_init('https://api.cloudinary.com/v1_1/'.rawurlencode($cloud).'/image/upload');curl_setopt_array($handle,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$payload,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);$body=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);
    $data=json_decode((string)$body,true);if($status<200||$status>=300||empty($data['secure_url']))throw new RuntimeException('Cloudinary rejected the image: '.($data['error']['message']??$error?:'unknown error'));return $data['secure_url'];
}
function shiprocket_token(): string {
    $email=env_value('SHIPROCKET_EMAIL');$password=env_value('SHIPROCKET_PASSWORD');if(!$email||!$password)throw new RuntimeException('Shiprocket is not configured.');
    if(!empty($_SESSION['shiprocket_token'])&&($_SESSION['shiprocket_token_until']??0)>time())return $_SESSION['shiprocket_token'];
    $data=shiprocket_request('POST','/auth/login',['email'=>$email,'password'=>$password],null);if(empty($data['token']))throw new RuntimeException('Could not authenticate with Shiprocket.');$_SESSION['shiprocket_token']=$data['token'];$_SESSION['shiprocket_token_until']=time()+86000;return $data['token'];
}
function shiprocket_request(string $method,string $endpoint,?array $body,?string $token): array {
    $url='https://apiv2.shiprocket.in/v1/external'.$endpoint;$handle=curl_init($url);$headers=['Content-Type: application/json'];if($token)$headers[]='Authorization: Bearer '.$token;curl_setopt_array($handle,[CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>25]);if($body!==null)curl_setopt($handle,CURLOPT_POSTFIELDS,json_encode($body));$response=curl_exec($handle);$status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);$error=curl_error($handle);curl_close($handle);$data=json_decode((string)$response,true)?:[];if($status<200||$status>=300)throw new RuntimeException('Shiprocket request failed: '.($data['message']??$error?:'HTTP '.$status));return $data;
}
function shiprocket_create_shipment(PDO $db,array $tenant,array $order): array {
    $token=shiprocket_token();$items=$db->prepare('SELECT * FROM order_items WHERE order_id=?');$items->execute([$order['id']]);$lines=array_map(fn($line)=>['name'=>$line['product_name'],'sku'=>$line['sku']?:'SKU-'.$line['product_id'],'units'=>(int)$line['quantity'],'selling_price'=>(float)$line['unit_price'],'discount'=>0,'tax'=>0,'hsn'=>''],$items->fetchAll());
    $payload=['order_id'=>$order['order_number'],'order_date'=>date('Y-m-d H:i'),'pickup_location'=>env_value('SHIPROCKET_PICKUP_LOCATION'),'billing_customer_name'=>$order['customer_name'],'billing_last_name'=>'','billing_address'=>$order['shipping_address'],'billing_city'=>$order['city'],'billing_pincode'=>$order['pincode'],'billing_state'=>$order['state'],'billing_country'=>'India','billing_email'=>$order['customer_email'],'billing_phone'=>$order['customer_phone'],'shipping_is_billing'=>true,'order_items'=>$lines,'payment_method'=>$order['payment_method']==='cod'?'COD':'Prepaid','sub_total'=>$order['total'],'length'=>10,'breadth'=>10,'height'=>10,'weight'=>0.5];
    return shiprocket_request('POST','/orders/create/adhoc',$payload,$token);
}
function shiprocket_track(string $awb): array { return shiprocket_request('GET','/courier/track/awb/'.rawurlencode($awb),null,shiprocket_token()); }
function fastrr_checkout_url(array $tenant, array $order): string {
    $base=env_value('FASTRR_CHECKOUT_URL');if(!$base)return '';
    // Fastrr merchants receive their own hosted-checkout URL/contract during activation. We pass only an opaque order reference.
    return rtrim($base,'/').'?merchant_id='.rawurlencode(env_value('FASTRR_MERCHANT_ID')).'&order_ref='.rawurlencode($order['order_number']);
}
