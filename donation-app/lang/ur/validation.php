<?php

declare(strict_types=1);

/*
 * Urdu validation messages for the public website and the app (Week 11). Most forms give their
 * own friendly messages (Form Request messages()); these cover the rest.
 */
return [
    'accepted' => ':attribute قبول کرنا ضروری ہے۔',
    'array' => ':attribute درست نہیں۔',
    'boolean' => ':attribute درست نہیں۔',
    'date' => ':attribute درست تاریخ نہیں۔',
    'after_or_equal' => ':attribute، :date یا اس کے بعد کی تاریخ ہونی چاہیے۔',
    'digits' => ':attribute :digits ہندسوں کا ہونا چاہیے۔',
    'email' => 'درست ای میل ایڈریس درج کریں۔',
    'file' => ':attribute فائل ہونی چاہیے۔',
    'image' => ':attribute تصویر ہونی چاہیے۔',
    'in' => 'منتخب :attribute درست نہیں۔',
    'integer' => ':attribute پورا عدد ہونا چاہیے۔',
    'max' => [
        'array' => ':attribute میں :max سے زیادہ چیزیں نہیں ہو سکتیں۔',
        'file' => ':attribute :max کلوبائٹ سے بڑی نہیں ہو سکتی۔',
        'numeric' => ':attribute :max سے زیادہ نہیں ہو سکتا۔',
        'string' => ':attribute :max حروف سے زیادہ نہیں ہو سکتا۔',
    ],
    'mimes' => ':attribute ان میں سے کسی قسم کی فائل ہونی چاہیے: :values۔',
    'min' => [
        'array' => ':attribute میں کم از کم :min چیزیں ہونی چاہییں۔',
        'file' => ':attribute کم از کم :min کلوبائٹ کی ہونی چاہیے۔',
        'numeric' => ':attribute کم از کم :min ہونا چاہیے۔',
        'string' => ':attribute کم از کم :min حروف کا ہونا چاہیے۔',
    ],
    'numeric' => ':attribute عدد ہونا چاہیے۔',
    'regex' => ':attribute درست شکل میں نہیں۔',
    'required' => ':attribute درج کرنا ضروری ہے۔',
    'required_if' => ':attribute درج کرنا ضروری ہے۔',
    'required_with' => ':attribute درج کرنا ضروری ہے۔',
    'required_without' => ':attribute درج کرنا ضروری ہے۔',
    'string' => ':attribute درست نہیں۔',
    'uuid' => 'آپ کے فارم کی میعاد ختم ہو گئی۔ براہِ کرم دوبارہ بھیجیں۔',

    'attributes' => [
        'amount' => 'رقم',
        'name' => 'نام',
        'phone' => 'موبائل نمبر',
        'email' => 'ای میل',
        'cnic' => 'شناختی کارڈ نمبر',
        'city' => 'شہر',
        'message' => 'پیغام',
        'subject' => 'موضوع',
        'reference' => 'ٹرانزیکشن آئی ڈی',
        'proof' => 'ثبوت',
        'payment_method' => 'ادائیگی کا طریقہ',
        'fund_id' => 'فنڈ',
        'campaign_id' => 'مہم',
        'dedication' => 'کی طرف سے / کی یاد میں',
        'identifier' => 'موبائل نمبر یا ای میل',
        'code' => 'کوڈ',
        'frequency' => 'دورانیہ',
        'starts_on' => 'پہلی تاریخ',
        'offering_id' => 'جانور',
        'names' => 'نام',
        'names.*' => 'نام',
    ],
];
