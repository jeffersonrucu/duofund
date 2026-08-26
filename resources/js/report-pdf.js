// html2pdf tem ~800KB. Antes vinha de CDN e era baixado ao abrir o relatório;
// aqui vira um chunk separado, buscado só quando o usuário clica em exportar.
window.loadHtml2Pdf = async () => (await import('html2pdf.js')).default;
