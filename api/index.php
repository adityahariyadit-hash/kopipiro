<?php
require_once __DIR__.'/../config.php';
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
$pdo = db();

function body_json(): array { $d=json_decode(file_get_contents('php://input'),true); return is_array($d)?$d:[]; }
function order_detail(PDO $pdo, int $id): array {
    $s=$pdo->prepare("SELECT o.*, u.name cashier_name FROM orders o LEFT JOIN users u ON u.id=o.cashier_id WHERE o.id=?");
    $s->execute([$id]); $o=$s->fetch();
    if(!$o) return [];
    $q=$pdo->prepare("SELECT product_name,price,quantity,line_total FROM order_items WHERE order_id=?"); $q->execute([$id]); $o['items']=$q->fetchAll(); return $o;
}
try {
switch ($action) {
case 'products':
    $q=$pdo->query("SELECT p.*, COALESCE(ROUND(AVG(r.rating),1),0) rating, COUNT(r.id) review_count FROM products p LEFT JOIN reviews r ON r.product_id=p.id WHERE p.is_active=1 GROUP BY p.id ORDER BY p.id DESC");
    json_out(['success'=>true,'products'=>$q->fetchAll()]);
case 'register':
case 'login':
    if($method!=='POST') json_out(['success'=>false,'message'=>'Method tidak diizinkan'],405);
    $d=body_json(); $email=filter_var($d['email']??'',FILTER_VALIDATE_EMAIL); $pass=$d['password']??'';
    if(!$email || strlen($pass)<6) json_out(['success'=>false,'message'=>'Email atau password tidak valid.'],422);
    if($action==='register') {
        $name=trim($d['name']??''); if($name==='') json_out(['success'=>false,'message'=>'Nama wajib diisi.'],422);
        $s=$pdo->prepare("INSERT INTO users(name,email,password,role) VALUES(?,?,?,'customer')");
        try {$s->execute([$name,$email,password_hash($pass,PASSWORD_DEFAULT)]);} catch(PDOException $e){json_out(['success'=>false,'message'=>'Email sudah digunakan.'],409);}
        $id=(int)$pdo->lastInsertId(); $_SESSION['user']=['id'=>$id,'name'=>$name,'email'=>$email,'role'=>'customer'];
    } else {
        $s=$pdo->prepare("SELECT id,name,email,password,role FROM users WHERE email=?"); $s->execute([$email]); $u=$s->fetch();
        if(!$u || !password_verify($pass,$u['password'])) json_out(['success'=>false,'message'=>'Email atau password salah.'],401);
        unset($u['password']); $_SESSION['user']=$u;
    }
    json_out(['success'=>true,'user'=>current_user()]);
case 'logout':
    $_SESSION=[]; session_destroy(); json_out(['success'=>true]);
case 'me':
    json_out(['success'=>true,'user'=>current_user()]);
case 'checkout':
    if($method!=='POST') json_out(['success'=>false,'message'=>'Method tidak diizinkan'],405);
    $d=body_json(); $items=$d['items']??[]; $name=trim($d['customer_name']??'');
    if(!$items || $name==='') json_out(['success'=>false,'message'=>'Keranjang dan nama wajib diisi.'],422);
    $user=current_user(); $subtotal=0; $validated=[];
    foreach($items as $it) {
        $pid=(int)($it['product_id']??0); $qty=(int)($it['quantity']??0);
        if($pid<1 || $qty<1 || $qty>50) json_out(['success'=>false,'message'=>'Item pesanan tidak valid.'],422);
        $s=$pdo->prepare("SELECT id,name,price FROM products WHERE id=? AND is_active=1"); $s->execute([$pid]); $p=$s->fetch();
        if(!$p) json_out(['success'=>false,'message'=>'Produk tidak tersedia.'],422);
        $line=(float)$p['price']*$qty; $subtotal+=$line; $validated[]=['product'=>$p,'qty'=>$qty,'line'=>$line];
    }
    $discount=$user && $user['role']==='customer' ? 10 : 0;
    $discountAmount=round($subtotal*$discount/100,2); $total=$subtotal-$discountAmount;
    $pdo->beginTransaction();
    $code='KPI-'.date('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    $s=$pdo->prepare("INSERT INTO orders(order_code,customer_id,customer_name,customer_phone,subtotal,discount_percent,discount_amount,total) VALUES(?,?,?,?,?,?,?,?)");
    $s->execute([$code,$user['id']??null,$name,trim($d['customer_phone']??''),$subtotal,$discount,$discountAmount,$total]); $oid=(int)$pdo->lastInsertId();
    $s=$pdo->prepare("INSERT INTO order_items(order_id,product_id,product_name,price,quantity,line_total) VALUES(?,?,?,?,?,?)");
    foreach($validated as $v) $s->execute([$oid,$v['product']['id'],$v['product']['name'],$v['product']['price'],$v['qty'],$v['line']]);
    $pdo->commit(); json_out(['success'=>true,'message'=>'Pesanan berhasil dibuat.','order_code'=>$code,'subtotal'=>$subtotal,'discount_percent'=>$discount,'discount_amount'=>$discountAmount,'total'=>$total]);
case 'orders':
    $u=require_role(['owner','cashier']);
    if($u['role']==='owner') $rows=$pdo->query("SELECT o.*, c.name cashier_name FROM orders o LEFT JOIN users c ON c.id=o.cashier_id ORDER BY o.created_at DESC")->fetchAll();
    else { $s=$pdo->prepare("SELECT o.*, c.name cashier_name FROM orders o LEFT JOIN users c ON c.id=o.cashier_id WHERE o.cashier_id=? ORDER BY o.created_at DESC"); $s->execute([$u['id']]); $rows=$s->fetchAll(); }
    foreach($rows as &$r) { $s=$pdo->prepare("SELECT product_name,quantity FROM order_items WHERE order_id=?"); $s->execute([$r['id']]); $r['items']=$s->fetchAll(); } unset($r);
    json_out(['success'=>true,'orders'=>$rows]);
case 'assign':
    $u=require_role(['owner']); $d=body_json(); $oid=(int)($d['order_id']??0); $cid=(int)($d['cashier_id']??0);
    $s=$pdo->prepare("SELECT id FROM users WHERE id=? AND role='cashier'"); $s->execute([$cid]); if(!$s->fetch()) json_out(['success'=>false,'message'=>'Akun kasir tidak ditemukan.'],422);
    $s=$pdo->prepare("UPDATE orders SET cashier_id=? WHERE id=?"); $s->execute([$cid,$oid]); json_out(['success'=>true]);
case 'cashiers':
    require_role(['owner']); $q=$pdo->query("SELECT id,name,email FROM users WHERE role='cashier' ORDER BY name"); json_out(['success'=>true,'cashiers'=>$q->fetchAll()]);
case 'status':
    $u=require_role(['owner','cashier']); $d=body_json(); $oid=(int)($d['order_id']??0); $status=$d['status']??'';
    if(!in_array($status,['pending','processing','completed','cancelled'],true)) json_out(['success'=>false,'message'=>'Status tidak valid.'],422);
    if($u['role']==='cashier') { $s=$pdo->prepare("UPDATE orders SET status=? WHERE id=? AND cashier_id=?"); $s->execute([$status,$oid,$u['id']]); }
    else { $s=$pdo->prepare("UPDATE orders SET status=? WHERE id=?"); $s->execute([$status,$oid]); }
    if(!$s->rowCount()) json_out(['success'=>false,'message'=>'Order tidak ditemukan atau bukan tugas Anda.'],404);
    json_out(['success'=>true]);
case 'summary':
    require_role(['owner']);
    $stats=$pdo->query("SELECT COUNT(*) orders_count, COALESCE(SUM(total),0) revenue, COALESCE(AVG(total),0) average_order FROM orders WHERE status<>'cancelled'")->fetch();
    $monthly=$pdo->query("SELECT DATE_FORMAT(created_at,'%Y-%m') month, SUM(total) revenue FROM orders WHERE status<>'cancelled' GROUP BY DATE_FORMAT(created_at,'%Y-%m') ORDER BY month DESC LIMIT 6")->fetchAll();
    json_out(['success'=>true,'stats'=>$stats,'monthly'=>array_reverse($monthly)]);
case 'report':
    require_role(['owner']);
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="laporan-kopipiro.csv"');
    echo "\xEF\xBB\xBF"; $out=fopen('php://output','w');
    fputcsv($out,['Kode Order','Tanggal','Pelanggan','Subtotal','Diskon (%)','Nominal Diskon','Total','Status','ID Kasir']);
    $q=$pdo->query("SELECT order_code,created_at,customer_name,subtotal,discount_percent,discount_amount,total,status,cashier_id FROM orders ORDER BY created_at DESC");
    while($r=$q->fetch()) fputcsv($out,$r); fclose($out); exit;
case 'admin_products':
    require_role(['owner']); $q=$pdo->query("SELECT * FROM products ORDER BY id DESC"); json_out(['success'=>true,'products'=>$q->fetchAll()]);
case 'add_product':
    require_role(['owner']); if($method!=='POST') json_out(['success'=>false,'message'=>'Method tidak diizinkan'],405);
    $name=trim($_POST['name']??''); $cat=$_POST['category']??'Coffee'; $desc=trim($_POST['description']??''); $price=(float)($_POST['price']??0);
    if($name==='' || $price<=0) json_out(['success'=>false,'message'=>'Nama dan harga wajib valid.'],422);
    $image=null;
    if(isset($_FILES['image']) && $_FILES['image']['error']===UPLOAD_ERR_OK) {
        $f=$_FILES['image']; if($f['size']>3*1024*1024) json_out(['success'=>false,'message'=>'Ukuran gambar maksimal 3 MB.'],422);
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
        if(!isset($allowed[$mime])) json_out(['success'=>false,'message'=>'Format gambar harus JPG, PNG, atau WEBP.'],422);
        $file=bin2hex(random_bytes(12)).'.'.$allowed[$mime]; if(!move_uploaded_file($f['tmp_name'],__DIR__.'/../uploads/'.$file)) json_out(['success'=>false,'message'=>'Upload gagal.'],500); $image='uploads/'.$file;
    }
    $s=$pdo->prepare("INSERT INTO products(name,category,description,price,image) VALUES(?,?,?,?,?)"); $s->execute([$name,$cat,$desc,$price,$image]); json_out(['success'=>true,'message'=>'Produk ditambahkan.']);
case 'delete_product':
    require_role(['owner']); $d=body_json(); $s=$pdo->prepare("UPDATE products SET is_active=0 WHERE id=?"); $s->execute([(int)($d['id']??0)]); json_out(['success'=>true]);
default: json_out(['success'=>false,'message'=>'Endpoint tidak ditemukan.'],404);
}
} catch(Throwable $e) {
    if($pdo->inTransaction()) $pdo->rollBack();
    json_out(['success'=>false,'message'=>'Terjadi kesalahan server.','detail'=> $e->getMessage()],500);
}
