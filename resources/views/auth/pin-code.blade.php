@extends('layouts.mplace')

@section('title', 'Код-пароль')

@section('content')
    <section id="home" class="home-wrapper section-b-space overflow-hidden">
    </section>

    <div class="premium-content" style="min-height:calc(100vh - 200px);display:flex;align-items:center;justify-content:center;">
        <div style="text-align:center;width:320px;">
            <h5 style="color:rgba(var(--dark-text),1);font-size:20px;margin-bottom:8px;">Код-пароль</h5>
            <p style="color:rgba(var(--content-color),1);font-size:14px;margin-bottom:24px;">Введите код-пароль для входа</p>
            <div class="pin-dots d-flex gap-3 justify-content-center mb-3" id="pinDots">
                <span></span><span></span><span></span><span></span>
            </div>
            <div id="pinError" style="color:rgba(var(--error-color),1);font-size:13px;margin-bottom:12px;min-height:18px;"></div>
            <div class="pin-keypad d-grid mb-3" id="pinKeypad" style="grid-template-columns:repeat(3,1fr);gap:10px;max-width:270px;margin:0 auto;">
                <button class="pin-key" data-pin="1">1</button>
                <button class="pin-key" data-pin="2">2</button>
                <button class="pin-key" data-pin="3">3</button>
                <button class="pin-key" data-pin="4">4</button>
                <button class="pin-key" data-pin="5">5</button>
                <button class="pin-key" data-pin="6">6</button>
                <button class="pin-key" data-pin="7">7</button>
                <button class="pin-key" data-pin="8">8</button>
                <button class="pin-key" data-pin="9">9</button>
                <div class="pin-key empty"></div>
                <button class="pin-key" data-pin="0">0</button>
                <button class="pin-key pin-key--delete" data-pin="back">
                    <svg width="57" height="40" viewBox="0 0 57 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M24.8857 12.7717L32.5148 19.9049M40.1439 27.038L32.5148 19.9049M32.5148 19.9049L40.1439 12.7717M32.5148 19.9049L24.8857 27.038M54.148 2.5H16.525L3.14795 19.0489L16.525 37.5H54.148V2.5Z" stroke="#F2A93E" stroke-width="5"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script nonce="{{ app('csp-nonce') }}">
(function(){
    var code='',dots=document.querySelectorAll('#pinDots span'),errEl=document.getElementById('pinError'),sending=false;
    function updateDots(){dots.forEach(function(d,i){d.classList.toggle('active',i<code.length)})}
    document.querySelectorAll('#pinKeypad .pin-key[data-pin]').forEach(function(btn){
        btn.addEventListener('click',function(){
            var val=btn.getAttribute('data-pin');
            if(!val)return;
            if(val==='back'){
                if(sending)return;
                code=code.slice(0,-1);updateDots();errEl.textContent='';
            }else{
                if(code.length>=4||sending)return;
                code+=val;updateDots();
                if(code.length===4)submit();
            }
        });
    });
    function submit(){
        sending=true;errEl.textContent='';
        fetch('/api/pin-code/verify',{
            method:'POST',
            headers:{'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},
            body:JSON.stringify({pin_code:code})
        }).then(function(r){return r.json()}).then(function(data){
            if(data.success){
                window.location.href='/profile';
            }else{
                errEl.textContent=data.message||'Неверный код';
                code='';updateDots();sending=false;
            }
        }).catch(function(){
            errEl.textContent='Ошибка соединения';
            code='';updateDots();sending=false;
        });
    }
})();
</script>
@endpush
