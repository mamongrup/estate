const defaultData={
 rates:{TRY:1,EUR:.027,USD:.029,GBP:.023,RUB:2.55,AED:.106},
 regions:[
  {id:1,name:'Kaş',province:'Antalya',image:'https://images.unsplash.com/photo-1590077428593-a55bb07c4665?auto=format&fit=crop&w=1000&q=85'},
  {id:2,name:'Bodrum',province:'Muğla',image:'https://images.unsplash.com/photo-1564501049412-61c2a3083791?auto=format&fit=crop&w=900&q=85'},
  {id:3,name:'Alaçatı',province:'İzmir',image:'https://images.unsplash.com/photo-1533105079780-92b9be482077?auto=format&fit=crop&w=900&q=85'},
  {id:4,name:'Fethiye',province:'Muğla',image:'https://images.unsplash.com/photo-1602002418082-a4443e081dd1?auto=format&fit=crop&w=900&q=85'},
  {id:5,name:'Kalkan',province:'Antalya',image:'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=900&q=85'}],
 listings:[
  {id:1,title:'Sonsuzluk Havuzlu Taş Villa',region:'Kaş',type:'Villa',status:'Satılık',price:38500000,rooms:'4+1',bath:4,area:310,featured:true,image:'https://images.unsplash.com/photo-1600607687920-4e2a09cf159d?auto=format&fit=crop&w=1200&q=85',description:'Deniz manzaralı, doğal taş mimarisi ve özel peyzajıyla ayrıcalıklı bir yaşam.'},
  {id:2,title:'Marinaya Yakın Modern Daire',region:'Bodrum',type:'Daire',status:'Satılık',price:18900000,rooms:'3+1',bath:2,area:165,featured:true,image:'https://images.unsplash.com/photo-1600566753086-00f18fb6b3ea?auto=format&fit=crop&w=1200&q=85',description:'Şehrin ritmine yakın, sakin ve rafine bir sahil yaşamı.'},
  {id:3,title:'Alaçatı Avlulu Müstakil Ev',region:'Alaçatı',type:'Villa',status:'Satılık',price:24750000,rooms:'3+1',bath:3,area:190,featured:true,image:'https://images.unsplash.com/photo-1600047509807-ba8f99d2cdde?auto=format&fit=crop&w=1200&q=85',description:'Taş duvarları ve huzurlu avlusuyla Alaçatı ruhunu taşıyan özgün ev.'},
  {id:4,title:'Çalış Sahili Rezidans Dairesi',region:'Fethiye',type:'Daire',status:'Kiralık',price:125000,rooms:'2+1',bath:2,area:120,featured:false,image:'https://images.unsplash.com/photo-1600566753190-17f0baa2a6c3?auto=format&fit=crop&w=1200&q=85',description:'Sahile yürüme mesafesinde, sosyal olanaklı seçkin rezidans.'},
  {id:5,title:'Kalkan Koyunda Özel Villa',region:'Kalkan',type:'Villa',status:'Satılık',price:52000000,rooms:'5+1',bath:5,area:380,featured:false,image:'https://images.unsplash.com/photo-1600607688960-e095ff83135c?auto=format&fit=crop&w=1200&q=85',description:'Koya hâkim konumu, tam mahremiyet ve çağdaş Akdeniz mimarisi.'},
  {id:6,title:'Yatırımlık Deniz Manzaralı Arsa',region:'Kaş',type:'Arsa',status:'Satılık',price:14500000,rooms:'—',bath:0,area:820,featured:false,image:'https://images.unsplash.com/photo-1500530855697-b586d89ba3ee?auto=format&fit=crop&w=1200&q=85',description:'Villa projesine uygun, yolu açık ve panoramik manzaralı arsa.'}]
};
function getEstateData(){const saved=localStorage.getItem('marevitaData');return saved?JSON.parse(saved):structuredClone(defaultData)}
function saveEstateData(data){localStorage.setItem('marevitaData',JSON.stringify(data))}
