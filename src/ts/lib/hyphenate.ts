abstract class Hyphenator{

	public hyphenate(selector:string){
		let e="[абвгдеёжзийклмнопрстуфхцчшщъыьэюя]";
		let t="[аеёиоуыэюя]";
		let n="[бвгджзклмнпрстфхцчшщ]";
		let r="[йъь]";
		let i="­";

		let s=new RegExp("("+r+")("+e+e+")","ig");
		let o=new RegExp("("+t+")("+t+e+")","ig");
		let u=new RegExp("("+t+n+")("+n+t+")","ig");
		let a=new RegExp("("+n+t+")("+n+t+")","ig");
		let f=new RegExp("("+t+n+")("+n+n+t+")","ig");
		let l=new RegExp("("+t+n+n+")("+n+n+t+")","ig");

		document.querySelectorAll(selector).forEach(el => {
			var e=el.innerHTML;
			e=e.replace(s,"$1"+i+"$2");
			e=e.replace(o,"$1"+i+"$2");
			e=e.replace(u,"$1"+i+"$2");
			e=e.replace(a,"$1"+i+"$2");
			e=e.replace(f,"$1"+i+"$2");
			e=e.replace(l,"$1"+i+"$2");
			el.innerHTML = e;
		});
	}
}

export default Hyphenator;