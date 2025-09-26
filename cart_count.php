
<?php
session_start();
$count = 0;
if(isset($_SESSION['cart'])){
    foreach($_SESSION['cart'] as $item){
        $count += $item['perfume_quantity'];
    }
}
echo $count;
