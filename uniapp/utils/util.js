//公共js，主要做表单验证，以及基本方法封装
import {
	request
} from './request.js'
import store from '@/store/index.js';
import { parseNoun as resolveNoun } from './nouns.js';
const utils = {
	/**
	 * 封装 uni.showToast
	 * @param {String} text - 需要显示的提示文字
	 * @param {String} icon - 图标，默认为 'none' (不显示图标)
	 * @param {Number} duration - 持续时间，默认为 2000ms
	 */
	toast: function(text, icon = 'none', duration = 2000) {
		// 确保 text 是字符串
		if (typeof text !== 'string') {
			console.error("Toast message must be a string.");
			return;
		}

		// 防止传入空字符串时也弹出提示
		if (!text.trim()) {
			return;
		}

		uni.showToast({
			title: text,
			icon: icon,
			duration: duration
		});
	},
	hexToRgb: function(hex, alpha) {
		if (/^#([A-Fa-f0-9]{3}){1,2}$/.test(hex)) {
			let c = hex.substring(1).split('');
			if (c.length === 3) {
				c = [c[0], c[0], c[1], c[1], c[2], c[2]];
			}
			c = '0x' + c.join('');
			return 'rgba(' + [(c >> 16) & 255, (c >> 8) & 255, c & 255].join(',') + ',' + alpha + ')';
		}
		return `rgba(214, 32, 31, ${alpha})`;
	},
	formatTime: function(seconds) {
		const hours = Math.floor(seconds / 3600);
		const minutes = Math.floor((seconds % 3600) / 60);
		const secs = Math.floor(seconds % 60);

		const pad = (num) => num.toString().padStart(2, '0');

		if (hours > 0) {
			return `${hours}:${pad(minutes)}:${pad(secs)}`;
		} else {
			return `${minutes}:${pad(secs)}`;
		}
	},
	formatPrice: function(price) {
		if (price === null || price === undefined) return '0';
		return price.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
	},
	isNullOrEmpty: function(value) {
		//是否为空
		return (value === null || value === '' || value === undefined) ? false : true;
	},
	hideLoading: function() {
		uni.hideLoading();
	},
	getScene: function(scene = "") {
		if (scene == "") return {}
		let res = {}
		let params = decodeURIComponent(scene).split("&")
		params.forEach(item => {
			let pram = item.split("=")
			res[pram[0]] = pram[1]
		})
		return res
	},
	trim: function(value) {
		//去空格
		return value.replace(/(^\s*)|(\s*$)/g, "");
	},
	copy: function(value) {
		//拷贝数据
		uni.setClipboardData({
			data: value,
			success: (e) => {
				uni.showToast({
					title: '复制成功'
				})
			}
		})
	},
	isMobile: function(value) {
		//是否为手机号
		return true;
		return /^1(3|4|5|7|8|9)\d{9}$/.test(value);
	},
	isFloat: function(value) {
		//金额，只允许保留两位小数
		return /^([0-9]*[.]?[0-9])[0-9]{0,1}$/.test(value);
	},
	isNum: function(value) {
		//是否全为数字
		return /^[0-9]+$/.test(value);
	},
	checkPwd: function(value) {
		//密码为8~20位数字和字母组合
		return /^(?![0-9]+$)(?![a-zA-Z]+$)[0-9A-Za-z]{8,20}$/.test(value);
	},
	formatNum: function(num) {
		//格式化手机号码
		if (utils.isMobile(num)) {
			num = num.replace(/^(\d{3})\d{4}(\d{4})$/, '$1****$2')
		}
		return num;
	},
	openLocation(lon, lat, address) {
		uni.openLocation({
			address: address,
			latitude: parseFloat(lat),
			longitude: parseFloat(lon)
		})
	},
	call(tel) {
		uni.makePhoneCall({
			phoneNumber: tel
		})
	},
	rmoney: function(money) {
		//金额格式化
		return parseFloat(money).toFixed(2).toString().split('').reverse().join('').replace(/(\d{3})/g, '$1,')
			.replace(
				/\,$/, '').split('').reverse().join('');
	},
	formatDate: function(formatStr, fdate) {

		debugger
		//日期格式化
		if (fdate) {
			if (~fdate.indexOf('.')) {
				fdate = fdate.substring(0, fdate.indexOf('.'));
			}
			fdate = fdate.toString().replace('T', ' ').replace(/\-/g, '/');
			var fTime, fStr = 'ymdhis';
			if (!formatStr)
				formatStr = "y-m-d h:i:s";
			if (fdate)
				fTime = new Date(fdate);
			else
				fTime = new Date();
			var month = fTime.getMonth() + 1;
			var day = fTime.getDate();
			var hours = fTime.getHours();
			var minu = fTime.getMinutes();
			var second = fTime.getSeconds();
			month = month < 10 ? '0' + month : month;
			day = day < 10 ? '0' + day : day;
			hours = hours < 10 ? ('0' + hours) : hours;
			minu = minu < 10 ? '0' + minu : minu;
			second = second < 10 ? '0' + second : second;
			var formatArr = [
				fTime.getFullYear().toString(),
				month.toString(),
				day.toString(),
				hours.toString(),
				minu.toString(),
				second.toString()
			]
			for (var i = 0; i < formatArr.length; i++) {
				formatStr = formatStr.replace(fStr.charAt(i), formatArr[i]);
			}
			return formatStr;
		} else {
			return "";
		}
	},
	/**
	 * author hp
	 * msg 判断是否为微信浏览器
	 */
	isWeChat: function() {
		// #ifdef H5
		var ua = navigator.userAgent.toLowerCase();
		if (ua.match(/MicroMessenger/i) == "micromessenger") {
			return true;
		}
		// #endif
		return false;
	},
	showModal: function(content, title) {
		return new Promise((resolve, reject) => {
			uni.showModal({
				title: title ? title : '提示',
				content: content,
				success: (e) => {
					return resolve(e)
				}
			})
		});
	},
	getSetting: function(key, defaultValue = "") {
		let setting = store.state.mall.setting;
		if (!setting) {
			return defaultValue;
		}
		return setting[key] ? setting[key] : defaultValue
	},
	// 品牌词库解析 —— 统一实现在 utils/nouns.js（读 vuex，三级兜底）
	parseNoun: function(key) {
		return resolveNoun(key);
	},
	getAsset: function(path) {
		const app = getApp().globalData;
		const assetPath = '/statics/app' + path; // 提取公共后缀，修改一处即可全局生效

		// #ifdef H5
		const hostname = window.location.hostname;
		const isLocalhost = hostname === 'localhost' || hostname === '127.0.0.1';

		// H5 线上环境：直接使用当前网页的源（协议 + 域名 + 端口）
		if (!isLocalhost) {
			return window.location.origin + assetPath;
		}
		// #endif

		// 默认情况（非 H5 端，或 H5 本地开发环境）：使用全局配置的后端域名
		return app.domain + assetPath;
	},
	timeago: function(dateTimeStamp) {
		dateTimeStamp = dateTimeStamp * 1000;
		//dateTimeStamp是一个时间毫秒，注意时间戳是秒的形式，在这个毫秒的基础上除以1000，就是十位数的时间戳。13位数的都是时间毫秒。
		let result;
		let minute = 1000 * 60; //把分，时，天，周，半个月，一个月用毫秒表示
		let hour = minute * 60;
		let day = hour * 24;
		let week = day * 7;
		let halfamonth = day * 15;
		let month = day * 30;
		let now = new Date().getTime(); //获取当前时间毫秒
		let diffValue = now - dateTimeStamp; //时间差
		if (diffValue < 0) {
			return;
		}
		let minC = diffValue / minute; //计算时间差的分，时，天，周，月
		let hourC = diffValue / hour;
		let dayC = diffValue / day;
		let weekC = diffValue / week;
		let monthC = diffValue / month;
		if (monthC >= 1 && monthC < 4) {
			result = " " + parseInt(monthC) + "月前";
		} else if (weekC >= 1 && weekC < 4) {
			result = " " + parseInt(weekC) + "周前";
		} else if (dayC >= 1 && dayC < 7) {
			result = " " + parseInt(dayC) + "天前";
		} else if (hourC >= 1 && hourC < 24) {
			result = " " + parseInt(hourC) + "小时前";
		} else if (minC >= 1 && minC < 60) {
			result = " " + parseInt(minC) + "分钟前";
		} else if (diffValue >= 0 && diffValue <= minute) {
			result = "刚刚";
		} else {
			let datetime = new Date();
			datetime.setTime(dateTimeStamp);
			let Nyear = datetime.getFullYear();
			let Nmonth =
				datetime.getMonth() + 1 < 10 ?
				"0" + (datetime.getMonth() + 1) :
				datetime.getMonth() + 1;
			let Ndate =
				datetime.getDate() < 10 ? "0" + datetime.getDate() : datetime.getDate();
			let Nhour =
				datetime.getHours() < 10 ?
				"0" + datetime.getHours() :
				datetime.getHours();
			let Nminute =
				datetime.getMinutes() < 10 ?
				"0" + datetime.getMinutes() :
				datetime.getMinutes();
			let Nsecond =
				datetime.getSeconds() < 10 ?
				"0" + datetime.getSeconds() :
				datetime.getSeconds();
			result = Nyear + "-" + Nmonth + "-" + Ndate;
		}
		return result;
	},
	showLoading(title) {
		title = title ? title : '加载中...';
		uni.showLoading({
			title: title
		})
		setTimeout(() => {
			uni.hideLoading();
		}, 1000)
	},
	/**
	 * [新增] 封装页面跳转
	 * @param {String} url - 跳转的URL
	 */
	go: function(url) {
		if (!url || typeof url !== 'string') {
			console.error('无效的URL:', url);
			return;
		}
		uni.navigateTo({
			url: url,
			fail(err) {
				console.error(`页面跳转失败: ${url}`, err);
				// 可以在这里增加一些降级处理，比如 toast 提示
				uni.showToast({
					title: '页面打开失败',
					icon: 'none'
				});
			}
		});
	}
}

module.exports = utils