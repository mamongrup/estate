(function(){
  const language=document.querySelector('#language');
  const currency=document.querySelector('#currency');
  const languageLabel=document.querySelector('#languageLabel');
  const currencyLabel=document.querySelector('#currencyLabel');
  const sync=(select,label)=>{if(select&&label)label.textContent=select.options[select.selectedIndex]?.textContent||select.value};
  if(language){
    language.value=localStorage.getItem('siteLanguage')||'tr';
    language.addEventListener('change',()=>{localStorage.setItem('siteLanguage',language.value);sync(language,languageLabel);location.reload()});
  }
  if(currency){
    currency.value=localStorage.getItem('currency')||'TRY';
    currency.addEventListener('change',()=>{localStorage.setItem('currency',currency.value);sync(currency,currencyLabel)});
  }
  sync(language,languageLabel);sync(currency,currencyLabel);
})();
