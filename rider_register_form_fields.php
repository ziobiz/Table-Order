<?php
// rider_register_form_fields.php - Rider 등록 신청서 공통 필드 (include from rider_register.php)
// 사용 전에 $req_key, $driver_required 가 정의되어 있어야 함
if (!isset($req_key) || !is_callable($req_key)) { $req_key = function($k) { return false; }; }
?>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">성 <?php if ($req_key('last_name')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
        <input type="text" name="last_name" <?php if ($req_key('last_name')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="홍">
    </div>
    <div>
        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">이름 <?php if ($req_key('first_name')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
        <input type="text" name="first_name" <?php if ($req_key('first_name')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="길동">
    </div>
</div>
<div>
    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">주소 <?php if ($req_key('address')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
    <input type="text" name="address" <?php if ($req_key('address')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="주소 입력">
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">전화번호 <?php if ($req_key('phone')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
        <input type="text" name="phone" <?php if ($req_key('phone')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="010-0000-0000">
    </div>
    <div>
        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">생년월일 <?php if ($req_key('birth_date')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
        <input type="date" name="birth_date" <?php if ($req_key('birth_date')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500">
    </div>
</div>
<div>
    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">이메일 <?php if ($req_key('email')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
    <input type="email" name="email" <?php if ($req_key('email')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="email@example.com">
</div>
<div>
    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">주민등록증 등 이미지 <?php if ($req_key('id_document')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
    <input type="file" name="id_document" accept="image/jpeg,image/png,image/gif,image/webp" class="w-full px-4 py-2 text-sm border border-slate-200 rounded-xl">
</div>
<div>
    <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">tax 정보 또는 주민등록번호 <?php if ($req_key('tax_id')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
    <input type="text" name="tax_id" <?php if ($req_key('tax_id')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="주민번호 또는 tax ID">
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <div>
        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">아이디 (로그인 ID) <?php if ($req_key('username')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
        <input type="text" name="username" <?php if ($req_key('username')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="rider1">
    </div>
    <div>
        <label class="block text-[10px] font-black text-slate-500 uppercase mb-1">패스워드 <?php if ($req_key('password')): ?><span class="text-rose-500">*</span><?php endif; ?></label>
        <input type="password" name="password" <?php if ($req_key('password')): ?>required<?php endif; ?> class="w-full px-4 py-2.5 rounded-xl border border-slate-200 text-sm font-bold outline-none focus:ring-2 focus:ring-amber-500" placeholder="비밀번호" autocomplete="new-password">
    </div>
</div>
