<?php
function convertToPersianNumbers($number) {
    $persian_numbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace(range(0, 9), $persian_numbers, $number);
}

function formatNumbersByCommas($number){
    return number_format($number);
}
?>