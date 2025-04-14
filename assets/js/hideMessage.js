// تابع برای مخفی کردن پیام‌ها بعد از 7 ثانیه
function hideMessages() {
    const errorMessages = document.querySelectorAll('.error-message');
    const successMessages = document.querySelectorAll('.success-message');

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