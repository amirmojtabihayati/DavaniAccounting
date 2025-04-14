// تابع تبدیل اعداد به اعداد فارسی
function convertToPersianNumbers(number) {
    const persianNumbers = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return number.toString().replace(/\d/g, (digit) => persianNumbers[digit]);
}
// تابع برای فرمت کردن اعداد با کاما
function numberWithCommas(x) {
    return x.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}   