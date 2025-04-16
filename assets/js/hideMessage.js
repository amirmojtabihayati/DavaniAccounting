// تابع برای مخفی کردن پیام‌ها بعد از 7 ثانیه
function hideMessages() {
    const errorMessages = document.querySelectorAll('.alert');
    const successMessages = document.querySelectorAll('.alert');

    errorMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.display = 'none';
        }, 7000);
    });

    successMessages.forEach(msg => {
        setTimeout(() => {
            msg.style.display = 'none';
        }, 7000);
    });
}
// فراخوانی تابع بعد از بارگذاری صفحه
window.onload = hideMessages;