let myImage = document.querySelector("img");

myImage.addEventListener("click", function () {
  let mySrc = myImage.getAttribute("src");
  if (mySrc === "images/imac.jpeg") {
    myImage.setAttribute("src", "images/bieng.jpeg");
  } else {
    myImage.setAttribute("src", "images/imac.jpeg");
  }
});
let myButton = document.querySelector("button");
let myHeading = document.querySelector("h1");
function setUserName() {
  let myName = prompt("Veuillez saisir votre nom.");
  localStorage.setItem("nom", myName);
  myHeading.textContent = "Les ISEKAI sont cool, " + myName;
};
if (!localStorage.getItem("nom")) {
  setUserName();
} else {
  let storedName = localStorage.getItem("nom");
  myHeading.textContent = "Les ISEKAI sont cool, " + storedName;
};
myButton.addEventListener("click", function () {
  setUserName();
});


