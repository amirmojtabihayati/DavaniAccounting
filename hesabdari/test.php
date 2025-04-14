<?php

$debt_title = $_POST['debt_title'];
$number_of_installments = $_POST['number_of_installments'];
$installment_dates = $_POST['installment_dates']; // تاریخ‌های اقساط
$manual_amounts = $_POST['manual_amounts']; // مبلغ دستی

print $debt_title . " ";
echo "";
echo $number_of_installments . " ";
echo "";
print_r($installment_dates); // برای نمایش آرایه
echo "";
print_r($manual_amounts); // برای نمایش آرایه
?>