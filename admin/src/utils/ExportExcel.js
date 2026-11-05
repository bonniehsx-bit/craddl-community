import {
  export_json_to_excel
} from '@/utils/Export2Excel.js'

export default function exportExcel(header, fields, data, name) {
// 2. 准备您的数据
  const myJsonData = data;
// 3. 定义您需要的 Excel 列
  const excelHeader = header;
// 4. 定义与表头一一对应的、从 JSON 数据中取值的“键路径”
  const excelDataKeys = fields;
// 5. 定义文件名
  const excelFilename = name;
// 6. 调用导出函数
  export_json_to_excel({
    header: excelHeader,
    jsonData: myJsonData,
    dataKeys: excelDataKeys,
    defaultTitle: excelFilename
  });
}


//export default exportExcel;
