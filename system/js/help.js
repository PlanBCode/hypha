/* Function: toggleinfobuttons
 * toggles the visibility of the information icones
 * @param none
 * @returns none
 */  
    function toggleinfobuttons(e){
        if (e.className.includes('clicked')) {
            e.className = e.className.replace(' clicked','');
        }
        else
        {
            e.className += ' clicked';
        }
        var lijst = document.getElementsByClassName('hyphaInfoButton');
        for (var x=0 ; x < lijst.length ; x++){
          if (lijst[x].style.display === "none") {
             lijst[x].style.display = "inline-block";
          } else {
             lijst[x].style.display = "none";
          }
        }
    }
    
    function getCoords(elem) {
      var box = elem.getBoundingClientRect();

      return {
        top: box.top + pageYOffset,
        left: box.left + pageXOffset
      };
    }

    function showNote(anchor, position, html) {

      var note = document.createElement('div');
      note.className = "note";
      document.body.append(note);

      positionAt(anchor, position, note);
      var coords = " top :"+ note.style.top +" left :" + note.style.left;
      note.innerHTML = html + coords;
    }

    function positionAt(anchor, position, elem) {

      var anchorCoords = getCoords(anchor);

      switch (position) {
        case "top-out":
          elem.style.left = anchorCoords.left + "px";
          elem.style.top = anchorCoords.top - elem.offsetHeight + "px";
          break;

        case "right-out":
          elem.style.left = anchorCoords.left + anchor.offsetWidth + "px";
          elem.style.top = anchorCoords.top + "px";
          break;

        case "bottom-out":
          elem.style.left = anchorCoords.left + "px";
          elem.style.top = anchorCoords.top + anchor.offsetHeight + "px";
          break;

        case "top-in":
          elem.style.left = anchorCoords.left + "px";
          elem.style.top = anchorCoords.top + "px";
          break;

        case "right-in":
          elem.style.width = '150px';
          elem.style.left = anchorCoords.left + anchor.offsetWidth - elem.offsetWidth + "px";
          elem.style.top = anchorCoords.top + "px";
          break;

        case "bottom-in":
          elem.style.left = anchorCoords.left + "px";
          elem.style.top = anchorCoords.top + anchor.offsetHeight - elem.offsetHeight + "px";
          break;
      }

    }


    function display(){
    var blockquote = document.querySelector('blockquote');
    var ruler1     = document.getElementById('ruler1');
    var ruler2     = document.getElementById('ruler2');
    showNote(blockquote, "top-in", "note top-in");
    showNote(blockquote, "top-out", "note top-out");
    showNote(blockquote, "right-out", "note right-out");
    showNote(blockquote, "bottom-in", "note bottom-in");
    showNote(ruler1,"right-in","note ruler1 right-in");
    showNote(ruler2,"top-in","note ruler2 bottom-in");
}
    
    function position(anchor,key,info){
        if (info.getAttribute('clicked')) {
            sluit(document.getElementById(info.getAttribute('clicked')));
            info.removeAttribute('clicked');
            info.className = info.className.replace(/ clicked/,''); 
            return;
        }
        //var noteHeight= "75";
        //var noteWidth = "150";
        var note = document.createElement('div');
        /* hypha */
	url = 'help.php?help=' + key;
	hypha_ajax(url, function() {
        var text = this;
        positionAt8(anchor,note,text);
        var b = document.createElement('button');
        var num = Math.floor(Math.random() * 10000) + 100; // returns a random integer from 100 to 200
        var id = "id" + num;
        info.setAttribute('clicked',id);
        info.className += ' clicked';
        b.setAttribute('onclick',("sluitAll("+ id + ")"));
        b.style.left= note.style.width;

        note.setAttribute('id',id);
        var t = document.createTextNode("X");     // Create a text node
        b.className="closebutton";
        b.appendChild(t);  
        note.appendChild(b);
        } );
	/* help info added, position the popup */

        //alert('xx'+ xx + ", note left" + note.style.left + ', yy' + yy + ", note class" + note.className);      
      // positie
      //var coords = "?"+ (sx - (xx + noteWidth/2 +20)) + ">0? left :" + note.style.left + ", width:" + note.style.width;
      //alert("coords" + coords);
      document.body.append(note);
    }
    
function sluitAll(e){
        var lijst = document.getElementsByClassName('hyphaInfoButton');
        for(var i = 0; i < lijst.length; i++){
            if (lijst[i].getAttribute('clicked') === e.id) {
                lijst[i].removeAttribute('clicked');
                lijst[i].className = lijst[i].className.replace(/ clicked/,'');
                sluit(e);
                return;
            }
        }
}

    function sluit(e){
        e.remove();
    }
// Handy JavaScript to measure the size taken to render the supplied text;
// you can supply additional style information too if you have it.
// pStyle: normal, italic, oblique

function measureText(pText, pFontSize, pStyle, pPadding) {
    var lDiv = document.createElement('div');
    if (pStyle !== null) {
        lDiv.style = pStyle;
    }
    lDiv.style.fontSize = "" + pFontSize + "px";
    lDiv.style.position = "absolute";
    lDiv.style.padding = pPadding + "px";
    //lDiv.style.border = "solid 1px";
    lDiv.style.left = -1000;
    lDiv.style.top = -1000;

    lDiv.innerHTML = pText;
    document.body.appendChild(lDiv);

    var lResult = {
        width: lDiv.clientWidth,
        height: lDiv.clientHeight
    };
    document.body.removeChild(lDiv);
    lDiv = null;

    return lResult;
}
    function hypha_ajax(url, callback) {
            // simple basic ajax request
      var httpRequest; // create our XMLHttpRequest object
      if (window.XMLHttpRequest) {
        httpRequest = new XMLHttpRequest();
      } else if (window.ActiveXObject) {
        // Internet Explorer
        httpRequest = new
        ActiveXObject("Microsoft.XMLHTTP");
      }
      httpRequest.onreadystatechange = function() {
        // inline function to check the status
        // of our request
        // this is called on every state change
        if (httpRequest.readyState === 4 &&
          httpRequest.status === 200) {
          callback.call(httpRequest.responseText);
          // call the callback function
        }
      };
      httpRequest.open('GET', url, true);
      httpRequest.send();

    }
    
    function positionAt8(anchor,note, key){
        var pSize = 12;
        var pStyle = "normal";
        var pPadding = 10;
        var x = anchor.clientX; // Get the horizontal coordinate 
        var y = anchor.clientY; // Get the vertical coordinate
        var xx = 0;
        var yy = 0;
        // determine if window is scrolled
        if (window.pageXOffset !== undefined) {
            // All browsers, except IE9 and earlier
            xx = x + window.pageXOffset;
            yy = y + window.pageYOffset;
        }
        else { // IE9 and earlier
            xx = x + document.documentElement.scrollLeft;
            yy = y + document.documentElement.scrollTop;
        }
        // get viewport
        var sx = document.documentElement.clientWidth;
        var windowCenter = sx/2;
        var sy = document.documentElement.clientHeight;
        var testText= 'gebied 5 '+ note.className + key + "<br>, midden:" + sx/2;
        var box = measureText(testText, pSize, pStyle, pPadding);
        note.style.fontSize = "" + 12 + "px";
        note.style.fontStyle = pStyle;
        note.style.padding = pPadding + "px";
        note.style.width = box.width + "px";
        note.style.height = box.height + "px";
        var noteWidth  = note.style.width.replace(/px/,'');
        var noteHeight = note.style.height.replace(/px/,'');
        //alert ('nieuwe waarden w=' + box.width + "(" + note.style.width + "), h="+ box.height + "(" + note.style.height + ")");
        if ((yy < noteHeight) && (xx < noteWidth))
        { // gebied 1
            note.style.left = xx +noteWidth/3 + "px";
            note.style.top  = yy + 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 1 '+ note.className + key + "<br>, midden:" + sx/2 ;
            //alert('gebied 1'+ note.class);
        }
        else if ((yy < noteHeight) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)))
        { // gebied 2
            note.style.left = xx + "px";
            note.style.top  = yy + 15 + "px";
            note.className = "notelo up";
            note.innerHTML = 'gebied 2 '+ note.className + key + "<br>, midden:" + sx/2;
            //alert('gebied 2'+ note.class);
        }
        else if ((yy < noteHeight) && ( xx > sx - noteWidth))
        {// gebied 3
            note.style.left = xx - noteWidth + "px";
            note.style.top  = yy + 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 3 '+ note.className + key + "<br>, midden:" + sx/2;
            //alert('gebied 3'+ note.className + key + ", midden:" + sx/2);
        }
        else if (((yy >= noteHeight) && (yy < sy - noteHeight)) && (xx < windowCenter) )
        { // gebied 4
            note.style.left = xx - 40+ noteWidth/2 + "px";
            note.style.top  = yy - 10 - noteHeight/2  + "px";
            note.className = "notelo left";
            note.innerHTML = 'gebied 4 '+ note.className + key + "<br>, midden:" + sx/2;
            //alert('gebied 4'+ note.class);
        }
        else if (((yy >= noteHeight) && (yy < sy - noteHeight)) && ( xx > windowCenter))       
        { // gebied 5}
            note.style.left = xx - noteWidth + "px";
            note.style.top  = yy - 10 - noteHeight/2 +  "px";
            note.className = "notelo right";
            note.innerHTML = 'gebied 5 '+ note.className + key + "<br>, midden:" + sx/2;

            //alert('gebied 6'+ note.className + key + ", midden:" + sx/2);
        }
        else if ((yy >= noteHeight) && (xx < noteWidth) )
        {//gebied 6
            note.style.left = xx + noteWidth/3  + "px";
            note.style.top  = yy - 10 - noteHeight + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 6 '+ note.className + key + ", midden:" + sx/2;
            //alert('gebied 7'+ note.className + key + ", midden:" + sx/2);
        }
        else if ((yy >= noteHeight) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)) )
        {//gebied 7
            note.style.left = xx - noteWidth/3 + "px";
            note.style.top  = yy - noteHeight - 40 + "px";
            note.className = "notelo down";
            note.innerHTML = 'gebied 7 '+ note.className + key + ", midden:" + sx/2;
            //alert('gebied 8'+ note.className + key + ", midden:" + sx/2);
        }
        else if ((yy >= noteHeight) && ( xx > sx - noteWidth))
        {//gebied 8
            note.style.left = xx - noteWidth + "px";
            note.style.top  = yy - noteHeight - 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 8 '+ note.className + key + ", midden:" + sx/2;
            //alert('gebied 9'+ note.className + key + ", midden:" + sx/2);
        }
      else alert('gebied niet te bepalen');
      }
      
        function positionAt9(anchor,note){
        var noteWidth  = note.style.width.replace(/px/,'');
        var noteHeight = note.style.height.replace(/px/,'');
        var x = anchor.clientX; // Get the horizontal coordinate 
        var y = anchor.clientY; // Get the vertical coordinate
        var xx = 0;
        var yy = 0;
        // determine if window is scrolled
        if (window.pageXOffset !== undefined) {
            // All browsers, except IE9 and earlier
            xx = x + window.pageXOffset;
            yy = y + window.pageYOffset;
        }
        else { // IE9 and earlier
            xx = x + document.documentElement.scrollLeft;
            yy = y + document.documentElement.scrollTop;
        }
        // get viewport
        var sx = document.documentElement.clientWidth;
        var sy = document.documentElement.clientHeight;
        if ((yy < noteHeight) && (xx < noteWidth))
        { // gebied 1
            note.style.left = xx + noteWidth/2 -10 + "px";
            note.style.top  = yy + 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 1 '+ note.className;
            //alert('gebied 1'+ note.class);
        }
        else if ((yy < noteHeight) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)))
        { // gebied 2
            note.style.left = xx + "px";
            note.style.top  = yy + 15 + "px";
            note.className = "notelo up";
            note.innerHTML = 'gebied 2 '+ note.className + ", midden:" + sx/2;
            //alert('gebied 2'+ note.class);
        }
        else if ((yy < noteHeight) && ( xx > sx - noteWidth))
        {// gebied 3
            note.style.left = xx - noteWidth/2 -10 + "px";
            note.style.top  = yy + 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 3 '+ note.className;
            //alert('gebied 3'+ note.className);
        }
        else if (((yy >= noteHeight) && (yy < sy - noteHeight)) && (xx < noteWidth) )
        { // gebied 4
            note.style.left = xx + noteWidth/2 + 15 + "px";
            note.style.top  = yy - noteHeight/2 + "px";
            note.className = "notelo left";
            note.innerHTML = 'gebied 4 '+ note.className;
            //alert('gebied 4'+ note.class);
        }
        else if (((yy >= noteHeight) && (yy < sy - noteHeight)) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)))
        {// gebied 5
            note.style.left = xx - 15 + "px";
            note.style.top  = yy - noteHeight - 15 + "px";
            note.className = "notelo down ";
            note.innerHTML = 'gebied 5 '+ note.className;
            //alert('gebied 5'+ note.className);
        }
        else if (((yy >= noteHeight) && (yy < sy - noteHeight)) && ( xx > sx - noteWidth))       
        { // gebied 6}
            note.style.left = xx - noteWidth/2 - 15 + "px";
            note.style.top  = yy - noteHeight/2 +  "px";
            note.className = "notelo right";
            note.innerHTML = 'gebied 6 '+ note.className;

            //alert('gebied 6'+ note.className);
        }
        else if ((yy >= noteHeight) && (xx < noteWidth) )
        {//gebied 7            
            note.style.left = xx  + noteWidth/2 - 15  + "px";
            note.style.top  = yy  - noteHeight  - 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 7 '+ note.className;
            //alert('gebied 7'+ note.className);
        }
        else if ((yy >= noteHeight) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)) )
        {//gebied 8            
            note.style.left = xx - 15 + "px";
            note.style.top  = yy -noteHeight -15 + "px";
            note.className = "notelo down";
            note.innerHTML = 'gebied 8 '+ note.className;
            //alert('gebied 8'+ note.className);
        }
        else if ((yy >= noteHeight) && ( xx > sx - noteWidth))
        {//gebied 9
            note.style.left = xx - noteWidth/2 + "px";
            note.style.top  = yy - noteHeight - 15 + "px";
            note.className = "notelo";
            note.innerHTML = 'gebied 9 '+ note.className;
            //alert('gebied 9'+ note.className);
        }
      else alert('gebied niet te bepalen');
      }

 /* positie bepalen
 * 
 * @param {type} anchor
 * @param {type} note
 * @returns {undefined}
 */
/* function:  showInfo
 * Parameters:
 * elem = element to position
 * position = left,top,right,bottom of elem
 *            default position of this info
 *            the position is modified if there is no room in window
*/    
    function positionInfo(anchor,note)
    {
        var noteHeight = note.style.height; //"75";
        var noteWidth = note.style.width; //"150";
        var x = anchor.clientX; // Get the horizontal coordinate 
        var y = anchor.clientY; // Get the vertical coordinate
        var xx = 0;
        var yy = 0;
        // determine if window is scrolled
        if (window.pageXOffset !== undefined) {
            // All browsers, except IE9 and earlier
            xx = x + window.pageXOffset;
            yy = y + window.pageYOffset;
        }
        else { // IE9 and earlier
            xx = x + document.documentElement.scrollLeft;
            yy = y + document.documentElement.scrollTop;
        }
        // get viewport
        var sx = document.documentElement.clientWidth;
        var sy = document.documentElement.clientHeight;
        if ((yy < noteHeight) && (xx < noteWidth))
        { // gebied 1
            note.style.left = xx + noteWidth + "px";
            note.style.top  = yy + noteHeight + "px";
            note.class = "notelo up";
        }
        if ((yy < noteHeight) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)))
        { // gebied 2
            note.style.left = xx + "px";
            note.style.top  = yy + noteHeight + "px";
            note.class = "notelo up";
        }
        if ((yy < noteHeight) && ( xx > sx - noteWidth))
        {// gebied 3
            note.style.left = xx - noteWidth -10 + "px";
            note.style.top  = yy + noteHeight + "px";
            note.class = "notelo right";
        }
        if (((noteHeight < yy) && (yy < sy - noteHeight)) && (xx < noteWidth) )
        { // gebied 4
            note.style.left = xx + noteWidth + "px";
            note.style.top  = yy + "px";
            note.class = "notelo left";
        }
        if (((noteHeight < yy) && (yy < sy - noteHeight)) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)))
        {// gebied 5
            note.style.left = xx + "px";
            note.style.top  = yy - noteHeight + "px";
            note.class = "notelo down";
        }
        if (((noteHeight < yy) && (yy < sy - noteHeight)) && ( xx > sx - noteWidth))       
        { // gebied 6}
            note.style.left = xx - noteWidth - 10 + "px";
            note.style.top  = yy + noteHeight + "px";
            note.class = "notelo right";
        }
        if ((yy > noteHeight) && (xx < noteWidth) )
        {//gebied 7            
            note.style.left = xx + noteWidth + "px";
            note.style.top  = yy - noteHeight + "px";
            note.class = "notelo down";
        }
        if ((yy > noteHeight) && (( noteWidth < xx) &&  ( xx < sx - noteWidth)) )
        {//gebied 8            
            note.style.left = xx + "px";
            note.style.top  = yy - noteHeight + "px";
            note.class = "notelo down";
        }
        if ((yy > noteHeight) && ( xx > sx - noteWidth))
        {//gebied 9
            note.style.left = xx - noteWidth - 10 + "px";
            note.style.top  = yy - noteHeight - 10 + "px";
            note.class = "notelo down";
        }
    }
