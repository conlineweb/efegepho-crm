// Get the modal
var modal = document.getElementById("myModal");
var modal_img = document.getElementById("Modal_img");

// Get the button that opens the modal
var btn = document.getElementById("myBtn");
var btn_img = document.getElementById("img_product");
var imgsrc = document.getElementById("img_product").src;
// Get the <span> element that closes the modal
var span = document.getElementsByClassName("close")[0];
// var spanx = document.getElementsByClassName("closex")[0];

// When the user clicks the button, open the modal 
btn.onclick = function() {
  modal.style.display = "block";
  
}
//btn_img.onclick = function() {
function modalimg(){
      
  document.getElementById("imgcorte").src=imgsrc;
  modal_img.style.display = "block";
  
}
// When the user clicks on <span> (x), close the modal
// span.onclick = function() {
//   modal.style.display = "none";
// }
// spanx.onclick = function() {
//   modal_img.style.display = "none";
// }

// When the user clicks anywhere outside of the modal, close it
window.onclick = function(event) {
  if (event.target == modal) {
    modal.style.display = "none";
    
  }else if (event.target == modal_img){
      modal_img.style.display = "none";
  }
}