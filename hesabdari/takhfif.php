<?php
include "../header.php";
?>
    <div class="container">
        <h1>تخفیف</h1>

        <form action="">
            <div class="jostojo">
                <span>جستجو:</span>
                <label for="">رشته:</label>
                <select> 
                    <option value="">یه گزینه انتخاب کنید</option>
                    <option value="">شبکه و نرم افزار</option>
                    <option value="">الکترونیک</option>
                    <option value="">الکتروتکنیک</option>
                </select>

                <label for="">پایه:</label>
                <select>
                    <option value="">یه گزینه انتخاب کنید</option>
                    <option value="">دهم</option>
                    <option value="">یازدهم</option>
                    <option value="">دوازدهم</option>
                </select>

                <label for="">کد ملی:</label>
                <input type="text">

                <label for=""> نام و نام خانوادگی:</label>
                <select id="exampleSelect" >
                    <option value="">یه گزینه انتخاب کنید</option>
                    <option value="option1">گزینه 1</option>
                    <option value="option2">گزینه 2</option>
                    <option value="option3">گزینه 3</option>
                    <option value="option4">گزینه 4</option>
                    <option value="option5">گزینه 5</option>
                </select>

               
            </div>
            
            <span style="color: black;font-size: 24px; margin-bottom: 30px; "> بدهی:</span>
            <span style="color: black;font-size: 24px; margin-bottom: 30px; "> مبلغ به حروف</span>

            <label for="">مبلغ تخفیف </label>
            <input type="text">

            

            <label for="">بابت</label>
            <select name="" id="">
                <option value=""> یه گزینه را انتخاب کنید</option>
                <option value="">کمیته امداد / بهزیستی</option>
                <option value="">فرهنگی</option>
                <option value="">خانواده ایثار گر</option>
                <option value="">اداری</option>
                <option value="">سایر...</option>
                
            </select>
            

            <label for="">شماره مجوز</label>
            <input type="text">


            <label for="">نوع واریز</label>
            <select name="" id="">
                <option value=""> یه گزینه را انتخاب کنید</option>
                <option value="">کارتخوان</option>
                <option value="">کارت به کارت</option>
                <option value="">فیش</option>
                <option value="">خودپرداز</option>
                <option value="">سایر...</option>
                
            </select>


            <button type="1">افزودن فایل اکسل</button>
            <button type="submit">افزودن</button>

            <button type="button">انصراف</button>
        </form>
    </div>
<?php
include "../footer.php";
?>