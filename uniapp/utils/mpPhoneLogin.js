/**
 * 小程序「手机号一键登录」—— 登录页和首页登录弹窗共用这一份,别再各写一遍。
 *
 * 流程:<button open-type="getPhoneNumber"> 拿到手机号 code → uni.login 拿 wxcode →
 * 后端 /api/login/login-by-mpwx-phone(服务端自己去微信换手机号,老用户直接登录、新用户自动注册)
 * → 存 token、拉用户信息(store user/loginSuccess)。
 *
 * 只在微信小程序里有意义;其它端调用直接 reject。
 *
 * @param {Object} detail  getphonenumber 事件的 e.detail
 * @param {Object} store   Vuex store(页面里传 this.$store)
 * @param {Function} request  this.$request
 * @returns {Promise<Object>} 成功 resolve 用户信息;失败 reject Error(message 可直接给用户看)
 */
export function loginByMpPhone(detail, store, request) {
	return new Promise((resolve, reject) => {
		// #ifndef MP-WEIXIN
		reject(new Error('请在微信小程序中使用'));
		return;
		// #endif
		// #ifdef MP-WEIXIN
		const { code, encryptedData, iv, errMsg } = detail || {};
		if (!code) {
			// 用户点了「拒绝」也会走到这里:errMsg 里带 deny / fail
			const denied = errMsg && (errMsg.indexOf('deny') > -1 || errMsg.indexOf('fail') > -1);
			reject(new Error(denied ? '已取消授权' : '授权失败'));
			return;
		}
		uni.login({
			provider: 'weixin',
			success: (loginRes) => {
				request({
					url: '/api/login/login-by-mpwx-phone',
					method: 'POST',
					data: { code, encryptedData, iv, wxcode: loginRes.code },
				}).then((res) => {
					if (res.code == 0 && res.data && res.data.access_token) {
						store.dispatch('user/loginSuccess', { token: res.data.access_token })
							.then(resolve)
							.catch(() => resolve());
					} else {
						reject(new Error(res.msg || '登录失败,请稍后再试'));
					}
				}).catch(() => reject(new Error('网络异常,请稍后再试')));
			},
			fail: () => reject(new Error('微信环境异常')),
		});
		// #endif
	});
}

/**
 * 首页登录弹窗(jx-login-sheet)里的主按钮是不是「手机号一键登录」。
 * 后台「商城设置 → 登录注册 → 开启首页登录 → 弹窗登录方式」,字段沿用 is_login_mobile
 * (它原来的说明就是「控制登录弹窗点立即登录的去向」,只是前端从没读过):
 *   '0'(默认)= 弹窗里直接手机号一键登录;'1' = 弹窗只给「去登录页」。
 * 只管弹窗;登录页本身照旧(小程序里手机号授权和账号密码两种都能选)。
 * 只在小程序里有意义,其它端一律 false。
 */
export function sheetUsesPhoneLogin(store) {
	// #ifdef MP-WEIXIN
	const s = (store && store.state.mall && store.state.mall.setting) || {};
	return String(s.is_login_mobile || '0') !== '1';
	// #endif
	// #ifndef MP-WEIXIN
	return false;
	// #endif
}
