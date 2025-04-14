var getJSON = function (url, callback) {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', url, true);
    xhr.responseType = 'json';
    xhr.onload = function () {
        var status = xhr.status;
        if (status === 200) {
            callback(null, xhr.response);
        } else {
            callback(status, xhr.response);
        }
    };
    xhr.send();
};

function convertToPersianDigits(num) {
            const persianDigits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return num.toString().split('').map(digit => {
                // اطمینان از اینکه فقط اعداد 0-9 تبدیل شوند
                if (digit >= '0' && digit <= '9') {
                    return persianDigits[digit];
                }
                return digit; // اگر کاراکتر غیر عددی باشد، همان را برمی‌گرداند
            }).join('');
        }

        function SetNumSystem(inputControlId) {
            var inputControl = document.getElementById(inputControlId);

            inputControl.addEventListener("input", function () {
                // تبدیل اعداد به اعداد فارسی
                inputControl.value = convertToPersianDigits(this.value);
            });
        }

        // استفاده از تابع
        SetNumSystem('debt_amount'); // فرض کنید ID فیلد ورودی شما 'debt_amount' است