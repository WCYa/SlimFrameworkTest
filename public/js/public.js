/*
 * Serialize all form data into a query string
 * (c) 2018 Chris Ferdinandi, MIT License, https://gomakethings.com
 * @param  {Node}   form The form to serialize
 * @return {String}      The serialized form data
 */
var serialize = function (form) {

	// Setup our serialized data
	var serialized = [];

	// Loop through each field in the form
	for (var i = 0; i < form.elements.length; i++) {

		var field = form.elements[i];

		// Don't serialize fields without a name, submits, buttons, file and reset inputs, and disabled fields
		if (!field.name || field.disabled || field.type === 'file' || field.type === 'reset' || field.type === 'submit' || field.type === 'button') continue;

		// If a multi-select, get all selections
		if (field.type === 'select-multiple') {
			for (var n = 0; n < field.options.length; n++) {
				if (!field.options[n].selected) continue;
				serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.options[n].value));
			}
		}

		// Convert field data to a query string
		else if ((field.type !== 'checkbox' && field.type !== 'radio') || field.checked) {
			serialized.push(encodeURIComponent(field.name) + "=" + encodeURIComponent(field.value));
		}
	}

	return serialized.join('&');
};


var ajaxForm = function (oForm) {
    if (!oForm.action || !oForm.method) { return; }
    if (oForm.method != "get" && oForm.method != "post" && oForm.method != "GET" && oForm.method != "POST") { return; }
    
    var xml;
    if(window.XMLHttpRequest)
    {
        xml = new XMLHttpRequest();
    }
    else{
        xml = new ActiveXObject("Microsoft.XMLHTTP");
    }

    xml.onreadystatechange = function(){
        if(xml.readyState==4 && xml.status==200)
        {
            //var data = JSON.parse(xml.responseText);
            var data = xml.responseText;
            if(data.length != 0)
                alert(data);
        } else if (xml.readyState==4) {
            alert("請求失敗: " + xml.status);
        }
    }
    if (oForm.method == "post") {
        xml.open("POST", oForm.action, "true");//url裡面為請求的地址，true表示非同步請求
        xml.setRequestHeader("Content-Type","application/x-www-form-urlencoded"); //設定post請求的請求頭
        xml.send(serialize(oForm));
    } else {
        xml.open("GET", oForm.action + "?" + serialize(oForm));
        xml.send(null);
    }
    
}

var objToXhrParam = function(obj) {
    var str = '';
    for (var key in obj) {
        if (str != "") {
            str += "&";
        }
        str += key + "=" + encodeURIComponent(obj[key]);
    }
    return str;
}

var ajax = function (obj) {
    if (!obj.url || !obj.method || !obj.data) { return; }
    var method = '';
    if (obj.method != "get" && obj.method != "post" && obj.method != "GET" && obj.method != "POST") { 
        method = 'get';
    } else {
        method = obj.method;
    }
    var async_bool = 'true';
    if (obj.async && obj.async == false) async_bool = 'false';
    var data = objToXhrParam(obj.data);
    var success = function(){};
    var error = function() {};
    if (obj.success) success = obj.success;
    if (obj.error) error = obj.error;
    
    // 開始處理 xMLHttpRequest
    
    var xml;
    if(window.XMLHttpRequest)
    {
        xml = new XMLHttpRequest();
    }
    else{
        xml = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xml.onreadystatechange = function(){
        if(xml.readyState==4 && xml.status==200)
        {
            //var data = JSON.parse(xml.responseText);
            var data = xml.responseText;
            if(data.length != 0) {
                success(data);
            }
        } else if (xml.readyState==4) {
            error("請求失敗: " + xml.status);
        }
    }
    if (method == "post" || method == "POST") {
        xml.open("POST", obj.url, async_bool);//url裡面為請求的地址，true表示非同步請求
        xml.setRequestHeader("Content-Type","application/x-www-form-urlencoded"); //設定post請求的請求頭
        xml.send(data);
    } else {
        xml.open("GET", obj.url + "?" + data, async_bool);
        xml.send(null);
    }
}

Date.prototype.Format = function (fmt) { //author: meizz 
    var o = {
        "M+": this.getMonth() + 1, //月份 
        "d+": this.getDate(), //日 
        "h+": this.getHours(), //小時
        "m+": this.getMinutes(), //分 
        "s+": this.getSeconds(), //秒 
        "q+": Math.floor((this.getMonth() + 3) / 3), //季度 
        "S": this.getMilliseconds() //毫秒 
    };
    if (/(y+)/.test(fmt)) fmt = fmt.replace(RegExp.$1, (this.getFullYear() + "").substr(4 - RegExp.$1.length));
    for (var k in o)
    if (new RegExp("(" + k + ")").test(fmt)) fmt = fmt.replace(RegExp.$1, (RegExp.$1.length == 1) ? (o[k]) : (("00" + o[k]).substr(("" + o[k]).length)));
    return fmt;
}

var isExistDate = function (str, format)
{
    var string = str;
    if(str.length == 8)
        string = (str.substring(0,4) + '-' + str.substring(4,6) + '-' + str.substring(6,8))
    var date = new Date(string);
    return (date instanceof Date && !isNaN(date.valueOf()) && date.Format(format) === str);
}

var setError = function (element, text)
{
  var span = document.createElement("span");
  span.className = "error";
  span.innerHTML = text;
  element.after(span);
  element.classList.add('error-border');
}
  
var clearError = function (element)
{
    var span = element.nextSibling;
    if (span.tagName == 'SPAN' && span.className == 'error')
        span.remove();
    element.classList.remove('error-border');
}

var objIsNull = function (obj) {
    if(typeof(obj) === 'object'){
        var json = JSON.stringify(obj);
        if(json === 'null')
            return true;
        else
            return false;
    } else {
        return false;
    }
}

/*
 *  Scroll to Top Button
 */
 
const scrollToTopBt = document.getElementById("scroll-to-top");
if(!objIsNull(scrollToTopBt)) {
    window.addEventListener("scroll", scrollFunction);
    scrollToTopBt.addEventListener("click", smoothScrollBackToTop);
}

function scrollFunction() {
    if (window.pageYOffset > 300) { // Show scrollToTopButton
        if (!scrollToTopBt.classList.contains("btnEntrance")) {
            scrollToTopBt.classList.remove("btnExit");
            scrollToTopBt.classList.add("btnEntrance");
            scrollToTopBt.style.display = "block";
        }
    } else { // Hide scrollToTopButton
        if (scrollToTopBt.classList.contains("btnEntrance")) {
            scrollToTopBt.classList.remove("btnEntrance");
            scrollToTopBt.classList.add("btnExit");
            setTimeout(function() {
                scrollToTopBt.style.display = "none";
            }, 250);
        }
    }
}

function scrollToTop() {
    window.scrollTo(0, 0);
}

/* 可使用 css 的 html { scroll-behavior: smooth } 
 * 或以下 javascript 產生滾動至頂
 */

function smoothScrollBackToTop() {
    const targetPosition = 0;
    const startPosition = window.pageYOffset;
    const distance = targetPosition - startPosition;
    const duration = 750;
    let start = null;
    
    window.requestAnimationFrame(step);
    
    function step(timestamp) {
        if(!start) start = timestamp;
        const progress = timestamp - start;
        window.scrollTo(0, easeInOutCubic(progress, startPosition, distance, duration));
        if (progress < duration) window.requestAnimationFrame(step);
    }
}

function easeInOutCubic(t, b, c, d) {
    t /= d/2;
    if (t < 1) return c/2*t*t*t + b;
    t -= 2;
    return c/2*(t*t*t + 2) + b;
}

/* SideBar & customScrollbar & dismiss & overlay */
var dismiss = document.getElementById('dismiss');
if(!objIsNull(dismiss)) {
    dismiss.addEventListener("click", function(){
        document.getElementById('content').classList.toggle('full');
        document.getElementById('sidebar').classList.toggle('hidden');
        document.getElementById('overlay').classList.toggle('active');
        
    });
}
var overlay = document.getElementById('overlay');
if(!objIsNull(overlay)) {
    overlay.addEventListener("click", function(){
        document.getElementById('sidebar').classList.remove('hidden');
        document.getElementById('overlay').classList.remove('active');
    });
}
var sidebarCollapse = document.getElementById('sidebarCollapse');
if(!objIsNull(sidebarCollapse)) {
    sidebarCollapse.addEventListener("click", function(){
        document.getElementById('content').classList.toggle('full');
        document.getElementById('sidebar').classList.toggle('hidden');
        document.getElementById('overlay').classList.toggle('active');
    });
}

var sidebar = document.getElementById('sidebar');
if(!objIsNull(sidebar)) {
    $("#sidebar").mCustomScrollbar({
        theme: "minimal",
        scrollInertia: 200
    });
}



