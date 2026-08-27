import 'bootstrap';
import 'bootstrap-icons/font/bootstrap-icons.css';

/** Escapes text before inserting API-driven labels into suggestion markup. */
const esc=(value='')=>String(value).replace(/[&<>'"]/g,ch=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));

/** Boots storefront mega-menu, autocomplete, vehicle picker, gallery and admin navigation without Alpine/Livewire. */
document.addEventListener('DOMContentLoaded',()=>{
    document.documentElement.classList.add('js-ready');
    const trigger=document.querySelector('[data-mega-trigger]'); const mega=document.querySelector('[data-mega-menu]');
    if(trigger&&mega){trigger.addEventListener('click',()=>mega.hidden=!mega.hidden); document.addEventListener('click',e=>{if(!mega.hidden&&!mega.contains(e.target)&&!trigger.contains(e.target))mega.hidden=true;});}

    const input=document.querySelector('[data-search-input]'); const box=document.querySelector('[data-search-suggestions]'); let timer;
    if(input&&box){input.addEventListener('input',()=>{clearTimeout(timer); const q=input.value.trim(); if(q.length<2){box.hidden=true;return;} timer=setTimeout(async()=>{try{const r=await fetch(`/api/v1/search/suggest?q=${encodeURIComponent(q)}`,{headers:{Accept:'application/json'}}); const json=await r.json(); box.innerHTML=(json.data||[]).map(x=>`<a href="/product/${esc(x.slug)}"><span>${esc(x.name)}<small> ${esc(x.sku)}</small></span><b>${Number(x.sale_price||0).toLocaleString('fa-IR')}</b></a>`).join(''); box.hidden=!json.data?.length;}catch{box.hidden=true;}},220);});}

    const picker=document.querySelector('[data-vehicle-picker]');
    if(picker){const make=picker.querySelector('[data-vehicle-make]'),model=picker.querySelector('[data-vehicle-model]'),generation=picker.querySelector('[data-vehicle-generation]'),trim=picker.querySelector('[data-vehicle-trim]'); const fill=(el,items,label,fmt=x=>x.name)=>{el.innerHTML=`<option value="">${label}</option>`+items.map(x=>`<option value="${x.id}">${esc(fmt(x))}</option>`).join('');el.disabled=false;}; make?.addEventListener('change',async()=>{model.disabled=generation.disabled=trim.disabled=true;if(!make.value)return;const j=await (await fetch(`/api/v1/vehicles/makes/${make.value}/models`)).json();fill(model,j.data,'مدل');}); model?.addEventListener('change',async()=>{generation.disabled=trim.disabled=true;if(!model.value)return;const j=await (await fetch(`/api/v1/vehicles/models/${model.value}/generations`)).json();fill(generation,j.data,'نسل',x=>`${x.name} ${x.from_year??''}-${x.to_year??''}`);}); generation?.addEventListener('change',async()=>{trim.disabled=true;if(!generation.value)return;const j=await (await fetch(`/api/v1/vehicles/generations/${generation.value}/trims`)).json();fill(trim,j.data,'سال / تیپ',x=>`${x.year} - ${x.name}`);});}

    document.querySelectorAll('[data-gallery-thumb]').forEach(btn=>btn.addEventListener('click',()=>{const main=document.querySelector('[data-main-image]');if(main)main.src=btn.dataset.src;}));
    const adminButton=document.querySelector('[data-admin-menu]'),sidebar=document.querySelector('[data-admin-sidebar]'); if(adminButton&&sidebar)adminButton.addEventListener('click',()=>sidebar.classList.toggle('open'));
});
