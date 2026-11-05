/* eslint-disable */
// 依赖项：确保您的项目中已经安装了 script-loader, file-saver, 和 xlsx
// npm install script-loader file-saver xlsx --save-dev

require('script-loader!file-saver');
// 如果您的环境需要 polyfill，可以引入 Blob.js。现代浏览器通常不需要。
// const { blob } = require('./Blob');
require('script-loader!xlsx/dist/xlsx.core.min');

/**
 * =================================================================================
 * A. 从 HTML TABLE 导出 EXCEL
 * =================================================================================
 */

/**
 * 解析 HTML table 元素并生成一个二维数组，处理 rowspan 和 colspan。
 * @param {DOMElement} table - The table element.
 * @returns {Array} - 返回一个包含 [二维数组, 合并单元格范围] 的数组。
 */
function generateArray(table) {
  var out = [];
  var rows = table.querySelectorAll('tr');
  var ranges = [];
  for (var R = 0; R < rows.length; ++R) {
    var outRow = [];
    var row = rows[R];
    var columns = row.querySelectorAll('td');
    for (var C = 0; C < columns.length; ++C) {
      var cell = columns[C];
      var colspan = cell.getAttribute('colspan');
      var rowspan = cell.getAttribute('rowspan');
      var cellValue = cell.innerText;
      if (cellValue !== "" && cellValue == +cellValue) cellValue = +cellValue;

      // 跳过被合并的单元格
      ranges.forEach(function (range) {
        if (R >= range.s.r && R <= range.e.r && outRow.length >= range.s.c && outRow.length <= range.e.c) {
          for (var i = 0; i <= range.e.c - range.s.c; ++i) outRow.push(null);
        }
      });

      // 处理 rowspan 和 colspan
      if (rowspan || colspan) {
        rowspan = rowspan || 1;
        colspan = colspan || 1;
        ranges.push({ s: { r: R, c: outRow.length }, e: { r: R + rowspan - 1, c: outRow.length + colspan - 1 } });
      }

      // 处理单元格值
      outRow.push(cellValue !== "" ? cellValue : null);

      // 根据 colspan 填充 null 值
      if (colspan) for (var k = 0; k < colspan - 1; ++k) outRow.push(null);
    }
    out.push(outRow);
  }
  return [out, ranges];
};

/**
 * 主函数：根据提供的 table ID 导出 Excel。
 * @param {string} id - The ID of the table element to export.
 */
export function export_table_to_excel(id) {
  var theTable = document.getElementById(id);
  var oo = generateArray(theTable);
  var ranges = oo[1];

  /* 原始数据 */
  var data = oo[0];
  var ws_name = "SheetJS";

  var wb = new Workbook(), ws = sheet_from_array_of_arrays(data);

  /* 添加合并范围到 worksheet */
  ws['!merges'] = ranges;

  /* 添加 worksheet 到 workbook */
  wb.SheetNames.push(ws_name);
  wb.Sheets[ws_name] = ws;

  var wbout = XLSX.write(wb, { bookType: 'xlsx', bookSST: false, type: 'binary' });

  saveAs(new Blob([s2ab(wbout)], { type: "application/octet-stream" }), "test.xlsx")
}


/**
 * =================================================================================
 * B. 从 JSON 数据导出 EXCEL (支持嵌套属性)
 * =================================================================================
 */

/**
 * 帮助函数：根据字符串路径从对象中获取嵌套属性值。
 * @param {object} obj - The source object.
 * @param {string} path - The property path, e.g., 'user.nickname'.
 * @returns {*} - The found value, or undefined if the path is invalid.
 */
function getValueByPath(obj, path) {
  // 使用 reduce 安全地访问嵌套属性
  return path.split('.').reduce((o, k) => (o && o[k] != null) ? o[k] : undefined, obj);
}

/**
 * 将 JSON 对象数组根据指定的键格式化为二维数组。
 * @param {Array<string>} dataKeys - 要导出的数据键数组, e.g., ['id', 'user.nickname'].
 * @param {Array<object>} jsonData - 原始 JSON 数据数组。
 * @returns {Array<Array<any>>} - 格式化后的二维数组。
 */
function formatJson(dataKeys, jsonData) {
  return jsonData.map(item => {
    return dataKeys.map(key => {
      // 使用帮助函数来获取值，支持嵌套路径
      const value = getValueByPath(item, key);
      // 如果值为 undefined 或 null，返回空字符串，Excel 中显示为空白单元格
      return value !== undefined && value !== null ? value : '';
    });
  });
}

/**
 * 主函数：导出 JSON 数据到 Excel。
 * @param {Array<string>} header - Excel 表头标题数组, e.g., ['ID', '用户昵称'].
 * @param {Array<object>} jsonData - 原始的 JSON 对象数组。
 * @param {Array<string>} dataKeys - 与表头对应的对象键名（或路径）数组, e.g., ['id', 'user.nickname'].
 * @param {string} defaultTitle - 导出的 Excel 文件名（不含扩展名）。
 */
export function export_json_to_excel({ header, jsonData, dataKeys, defaultTitle = '列表' }) {
  /* 1. 格式化 JSON 数据为二维数组 */
  var data = formatJson(dataKeys, jsonData);

  /* 2. 将表头添加到数据的第一行 */
  data.unshift(header);

  var ws_name = "SheetJS";

  var wb = new Workbook(),
    ws = sheet_from_array_of_arrays(data);

  /* 3. （可选）设置列宽 */
  // var colWidths = header.map(() => ({ wch: 20 })); // 示例：设置所有列宽为20
  // ws['!cols'] = colWidths;

  /* 4. 将工作表添加到工作簿 */
  wb.SheetNames.push(ws_name);
  wb.Sheets[ws_name] = ws;

  var wbout = XLSX.write(wb, { bookType: 'xlsx', bookSST: false, type: 'binary' });
  var title = defaultTitle || '列表';
  saveAs(new Blob([s2ab(wbout)], { type: "application/octet-stream" }), title + ".xlsx");
}


/**
 * =================================================================================
 * C. XLSX 库的核心辅助函数
 * (这些函数通常不需要修改)
 * =================================================================================
 */

function datenum(v, date1904) {
  if (date1904) v += 1462;
  var epoch = Date.parse(v);
  return (epoch - new Date(Date.UTC(1899, 11, 30))) / (24 * 60 * 60 * 1000);
}

function sheet_from_array_of_arrays(data, opts) {
  var ws = {};
  var range = { s: { c: 10000000, r: 10000000 }, e: { c: 0, r: 0 } };
  for (var R = 0; R != data.length; ++R) {
    for (var C = 0; C != data[R].length; ++C) {
      if (range.s.r > R) range.s.r = R;
      if (range.s.c > C) range.s.c = C;
      if (range.e.r < R) range.e.r = R;
      if (range.e.c < C) range.e.c = C;
      var cell = { v: data[R][C] };
      if (cell.v == null) continue;
      var cell_ref = XLSX.utils.encode_cell({ c: C, r: R });

      if (typeof cell.v === 'number') cell.t = 'n';
      else if (typeof cell.v === 'boolean') cell.t = 'b';
      else if (cell.v instanceof Date) {
        cell.t = 'n';
        cell.z = XLSX.SSF._table[14];
        cell.v = datenum(cell.v);
      }
      else cell.t = 's';

      ws[cell_ref] = cell;
    }
  }
  if (range.s.c < 10000000) ws['!ref'] = XLSX.utils.encode_range(range);
  return ws;
}

function Workbook() {
  if (!(this instanceof Workbook)) return new Workbook();
  this.SheetNames = [];
  this.Sheets = {};
}

function s2ab(s) {
  var buf = new ArrayBuffer(s.length);
  var view = new Uint8Array(buf);
  for (var i = 0; i != s.length; ++i) view[i] = s.charCodeAt(i) & 0xFF;
  return buf;
}
