/*

 Hal Nichols JavaScript functions

 */

//slide show function
var currentIndex = 1;

var nextIndex = 1;

function slideShow(){
	console.log(nextIndex);

	nextIndex = currentIndex+1;

	if (nextIndex == 4){
		nextIndex = 1;
	}

	var imgNext = "#img0"+nextIndex;

	var imgCurrent = "#img0"+currentIndex;

	$(imgNext).animate({opacity: 1.0}, 2000);

	$(imgCurrent).animate({opacity: 0.0}, 2000);

	currentIndex = nextIndex;
}
