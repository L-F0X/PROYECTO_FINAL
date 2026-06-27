import React, { useState } from "react";
import { 
  Database, 
  Search, 
  Trash2, 
  Plus, 
  Copy, 
  Check, 
  Info, 
  Filter, 
  User, 
  Tag, 
  Layers, 
  FileText, 
  Briefcase, 
  ShieldAlert 
} from "lucide-react";
import { RolNombre, Usuario, LoteRequerimiento, MatrizItem, Necesidad, CodigoUnspsc, Proveedor, CertificadoExistencia, Cotizacion, IvaCotizacion, FichaTecnica } from "../types";

interface DatabaseExplorerProps {
  roles: { ID_ROL: number; NOMBRE_ROL: RolNombre }[];
  onUpdateRoles: (updated: { ID_ROL: number; NOMBRE_ROL: RolNombre }[]) => void;
  usuarios: Usuario[];
  onUpdateUsuarios: (updated: Usuario[]) => void;
  lotes: LoteRequerimiento[];
  onUpdateLotes: (updated: LoteRequerimiento[]) => void;
  matrizItems: MatrizItem[];
  onUpdateMatrizItems: (updated: MatrizItem[]) => void;
  necesidades: Necesidad[];
  onUpdateNecesidades: (updated: Necesidad[]) => void;
  codigosUnspsc: CodigoUnspsc[];
  onUpdateCodigosUnspsc: (updated: CodigoUnspsc[]) => void;
  proveedores: Proveedor[];
  onUpdateProveedores: (updated: Proveedor[]) => void;
  certificados: CertificadoExistencia[];
  onUpdateCertificados: (updated: CertificadoExistencia[]) => void;
  cotizaciones: Cotizacion[];
  onUpdateCotizaciones: (updated: Cotizacion[]) => void;
  ivas: IvaCotizacion[];
  onUpdateIvas: (updated: IvaCotizacion[]) => void;
  fichasTecnicas: FichaTecnica[];
  onUpdateFichasTecnicas: (updated: FichaTecnica[]) => void;
  auditLogs: any[];
  onUpdateAuditLogs: (updated: any[]) => void;
}

export default function DatabaseExplorer({
  roles,
  onUpdateRoles,
  usuarios,
  onUpdateUsuarios,
  lotes,
  onUpdateLotes,
  matrizItems,
  onUpdateMatrizItems,
  necesidades,
  onUpdateNecesidades,
  codigosUnspsc,
  onUpdateCodigosUnspsc,
  proveedores,
  onUpdateProveedores,
  certificados,
  onUpdateCertificados,
  cotizaciones,
  onUpdateCotizaciones,
  ivas,
  onUpdateIvas,
  fichasTecnicas,
  onUpdateFichasTecnicas,
  auditLogs,
  onUpdateAuditLogs
}: DatabaseExplorerProps) {
  // Currently active table
  const [selectedTable, setSelectedTable] = useState<string>("USUARIO");
  const [searchQuery, setSearchQuery] = useState<string>("");
  const [copied, setCopied] = useState<boolean>(false);
  const [showAddForm, setShowAddForm] = useState<boolean>(false);

  // Form states for each table
  const [formUsuario, setFormUsuario] = useState({ DOCUMENTO: "", NOMBRE: "", APELLIDO: "", EMAIL: "", ID_ROL: "1", ESTADO: "ACTIVO" as "ACTIVO" | "INACTIVO" });
  const [formLote, setFormLote] = useState({ LOTE_NAME: "", ID_SOLICITANTE: "101", ID_INSTRUCTOR_APOYO: "102" });
  const [formMatriz, setFormMatriz] = useState({ ID_LOTE: "", DESCRIPCIÓN_BIEN: "", UNIDAD_MEDIDA: "Unidad", CANTIDAD_REGULAR: "1", VALOR_UNITARIO_PROMEDIO: "1000" });
  const [formNecesidad, setFormNecesidad] = useState({ ID_MATRIZ: "", CANTIDAD_REGULAR: "1", CANTIDAD_VULNERABLE: "0", CANTIDAD_MEDIA_TECNICA: "0", CANTIDAD_FIC: "0", CANTIDAD_CAMPESINA_COMPLEMENTARIA: "0" });
  const [formUnspsc, setFormUnspsc] = useState({ ID_MATRIZ_ITEM: "", SEGMENTO: "", FAMILIA: "", CLASE: "" });
  const [formProveedor, setFormProveedor] = useState({ NIT: "", RAZON_SOCIAL: "", EMAIL: "" });
  const [formCertificado, setFormCertificado] = useState({ NUMERO_CERTIFICADO: "", ID_LOTE: "", COMENTARIOS: "" });
  const [formCotizacion, setFormCotizacion] = useState({ ID_MATRIZ_ITEM: "", ID_PROVEEDOR: "", VALOR_UNITARIO: "" });
  const [formIva, setFormIva] = useState({ ID_COTIZACON: "", PERCENTAJE_IVA: "19", NUMERO_OFERTA: "OFERTA_1" as "OFERTA_1" | "OFERTA_2" | "OFERTA_3" });
  const [formFicha, setFormFicha] = useState({ NOMBRE_ITEM: "", CODIGO_UNSPSC: "", DENOMINACIÓN_TECNICA_BIEN: "", UNIDAD_MEDIDA: "Unidad", DESCRIPCIÓN_GENERAL: "", MARCA_OFRECIDA: "", FIRMA_PROPONENTE: "" });
  const [formRol, setFormRol] = useState({ ID_ROL: "", NOMBRE_ROL: RolNombre.INSTRUCTOR });

  const tablesList = [
    { id: "ROL", name: "1. Roles (ROL)", count: roles.length, desc: "Define los perfiles de acceso autorizados en el flujo precontractual.", pk: "ID_ROL" },
    { id: "USUARIO", name: "2. Usuarios (USUARIO)", count: usuarios.length, desc: "Funcionarios del SENA e instructores del centro de formación.", pk: "ID_USUARIO", fk: "ID_ROL" },
    { id: "LOTE_REQUERIMIENTO", name: "3. Lotes (LOTE_REQUERIMIENTO)", count: lotes.length, desc: "Agrupación de necesidades por programa o centro de formación.", pk: "ID_LOTE", fk: "ID_SOLICITANTE, ID_INSTRUCTOR_APOYO" },
    { id: "MATRIZ_ITEM", name: "4. Matriz Bienes (MATRIZ_ITEM)", count: matrizItems.length, desc: "Especificaciones y precios de referencia para cada ítem solicitado.", pk: "ID_MATRIZ_ITEM", fk: "ID_LOTE" },
    { id: "NECESIDAD", name: "5. Poblaciones (NECESIDAD)", count: necesidades.length, desc: "Distribución de cantidades por poblaciones (FIC, Campesina, Vulnerable, Regular).", pk: "ID_NECESIDAD", fk: "ID_MATRIZ" },
    { id: "CODIGO_UNSPSC", name: "6. UNSPSC (CODIGO_UNSPSC)", count: codigosUnspsc.length, desc: "Clasificación oficial de compras de las Naciones Unidas.", pk: "ID_CODIGO", fk: "ID_MATRIZ_ITEM" },
    { id: "PROVEEDOR", name: "7. Proveedores (PROVEEDOR)", count: proveedores.length, desc: "Entidades jurídicas u oferentes autorizados en el mercado local.", pk: "ID_PROVEEDOR" },
    { id: "CERTIFICADO_EXISTENCIA", name: "8. Certificados (CERTIFICADO_EXISTENCIA)", count: certificados.length, desc: "Certificación oficial de no existencia física en el inventario del almacén.", pk: "ID_CERTIFICADO", fk: "ID_LOTE" },
    { id: "COTIZACION", name: "9. Cotizaciones (COTIZACION)", count: cotizaciones.length, desc: "Ofertas comerciales con valores unitarios propuestas por los oferentes.", pk: "ID_COTIZACION", fk: "ID_MATRIZ_ITEM, ID_PROVEEDOR" },
    { id: "IVA_COTIZACION", name: "10. IVA (IVA_COTIZACION)", count: ivas.length, desc: "Cálculo y desglose del impuesto al valor agregado por cada oferta.", pk: "ID_IVA", fk: "ID_COTIZACON" },
    { id: "FICHA_TECNICA", name: "11. Fichas (FICHA_TECNICA)", count: fichasTecnicas.length, desc: "Anexo técnico con marcas, características del fabricante y firmas oficiales.", pk: "ID_FICHA_TECNICA" },
    { id: "AUDITORIA", name: "12. Auditoría (AUDITORIA)", count: auditLogs.length, desc: "Bitácora consecutiva de operaciones realizadas en caliente.", pk: "id" }
  ];

  // Log action in audit logs
  const addAuditLog = (accion: string, tabla: string, detalle: string) => {
    const newLog = {
      id: Date.now(),
      usuario: "Administrador BD",
      accion,
      tabla,
      detalle,
      fecha: new Date().toISOString().replace("T", " ").substring(0, 19)
    };
    onUpdateAuditLogs([newLog, ...auditLogs]);
  };

  // Delete handler
  const handleDeleteRow = (idField: string, idVal: number | string) => {
    if (!window.confirm(`¿Está seguro de eliminar el registro con ${idField} = ${idVal}?`)) return;

    switch (selectedTable) {
      case "ROL":
        onUpdateRoles(roles.filter(r => r.ID_ROL !== idVal));
        addAuditLog("ELIMINAR", "ROL", `Eliminado rol ID: ${idVal}`);
        break;
      case "USUARIO":
        onUpdateUsuarios(usuarios.filter(u => u.ID_USUARIO !== idVal));
        addAuditLog("ELIMINAR", "USUARIO", `Eliminado usuario ID: ${idVal}`);
        break;
      case "LOTE_REQUERIMIENTO":
        onUpdateLotes(lotes.filter(l => l.ID_LOTE !== idVal));
        addAuditLog("ELIMINAR", "LOTE_REQUERIMIENTO", `Eliminado lote ID: ${idVal}`);
        break;
      case "MATRIZ_ITEM":
        onUpdateMatrizItems(matrizItems.filter(m => m.ID_MATRIZ_ITEM !== idVal));
        addAuditLog("ELIMINAR", "MATRIZ_ITEM", `Eliminado item matriz ID: ${idVal}`);
        break;
      case "NECESIDAD":
        onUpdateNecesidades(necesidades.filter(n => n.ID_NECESIDAD !== idVal));
        addAuditLog("ELIMINAR", "NECESIDAD", `Eliminada necesidad ID: ${idVal}`);
        break;
      case "CODIGO_UNSPSC":
        onUpdateCodigosUnspsc(codigosUnspsc.filter(c => c.ID_CODIGO !== idVal));
        addAuditLog("ELIMINAR", "CODIGO_UNSPSC", `Eliminado código UNSPSC ID: ${idVal}`);
        break;
      case "PROVEEDOR":
        onUpdateProveedores(proveedores.filter(p => p.ID_PROVEEDOR !== idVal));
        addAuditLog("ELIMINAR", "PROVEEDOR", `Eliminado proveedor ID: ${idVal}`);
        break;
      case "CERTIFICADO_EXISTENCIA":
        onUpdateCertificados(certificados.filter(c => c.ID_CERTIFICADO !== idVal));
        addAuditLog("ELIMINAR", "CERTIFICADO_EXISTENCIA", `Eliminado certificado ID: ${idVal}`);
        break;
      case "COTIZACION":
        onUpdateCotizaciones(cotizaciones.filter(c => c.ID_COTIZACION !== idVal));
        addAuditLog("ELIMINAR", "COTIZACION", `Eliminada cotización ID: ${idVal}`);
        break;
      case "IVA_COTIZACION":
        onUpdateIvas(ivas.filter(i => i.ID_IVA !== idVal));
        addAuditLog("ELIMINAR", "IVA_COTIZACION", `Eliminado IVA cotización ID: ${idVal}`);
        break;
      case "FICHA_TECNICA":
        onUpdateFichasTecnicas(fichasTecnicas.filter(f => f.ID_FICHA_TECNICA !== idVal));
        addAuditLog("ELIMINAR", "FICHA_TECNICA", `Eliminada ficha técnica ID: ${idVal}`);
        break;
      case "AUDITORIA":
        onUpdateAuditLogs(auditLogs.filter(a => a.id !== idVal));
        break;
    }
  };

  // Submit Handler
  const handleAddRow = (e: React.FormEvent) => {
    e.preventDefault();
    const newId = Date.now();

    switch (selectedTable) {
      case "ROL":
        if (!formRol.ID_ROL) return;
        onUpdateRoles([...roles, { ID_ROL: Number(formRol.ID_ROL), NOMBRE_ROL: formRol.NOMBRE_ROL }]);
        addAuditLog("INSERTAR", "ROL", `Insertado Rol ${formRol.NOMBRE_ROL}`);
        setFormRol({ ID_ROL: "", NOMBRE_ROL: RolNombre.INSTRUCTOR });
        break;
      case "USUARIO":
        if (!formUsuario.DOCUMENTO || !formUsuario.NOMBRE) return;
        const newUsr: Usuario = {
          ID_USUARIO: newId,
          DOCUMENTO: formUsuario.DOCUMENTO,
          NOMBRE: formUsuario.NOMBRE,
          APELLIDO: formUsuario.APELLIDO,
          EMAIL: formUsuario.EMAIL,
          ID_ROL: Number(formUsuario.ID_ROL),
          ESTADO: formUsuario.ESTADO
        };
        onUpdateUsuarios([...usuarios, newUsr]);
        addAuditLog("INSERTAR", "USUARIO", `Creado usuario ${newUsr.NOMBRE} ${newUsr.APELLIDO}`);
        setFormUsuario({ DOCUMENTO: "", NOMBRE: "", APELLIDO: "", EMAIL: "", ID_ROL: "1", ESTADO: "ACTIVO" });
        break;
      case "LOTE_REQUERIMIENTO":
        if (!formLote.LOTE_NAME) return;
        const newLote: LoteRequerimiento = {
          ID_LOTE: newId,
          ID_SOLICITANTE: Number(formLote.ID_SOLICITANTE),
          ID_INSTRUCTOR_APOYO: Number(formLote.ID_INSTRUCTOR_APOYO),
          LOTE_NOMBRE: formLote.LOTE_NAME,
          ESTADO_TRAMITE: "BORRADOR",
          FECHA_CREACIÓN: new Date().toISOString().split("T")[0]
        };
        onUpdateLotes([newLote, ...lotes]);
        addAuditLog("INSERTAR", "LOTE_REQUERIMIENTO", `Creado lote ${newLote.LOTE_NOMBRE}`);
        setFormLote({ LOTE_NAME: "", ID_SOLICITANTE: "101", ID_INSTRUCTOR_APOYO: "102" });
        break;
      case "MATRIZ_ITEM":
        if (!formMatriz.DESCRIPCIÓN_BIEN || !formMatriz.ID_LOTE) return;
        const qty = Number(formMatriz.CANTIDAD_REGULAR) || 1;
        const price = Number(formMatriz.VALOR_UNITARIO_PROMEDIO) || 0;
        const newMat: MatrizItem = {
          ID_MATRIZ_ITEM: newId,
          ID_LOTE: Number(formMatriz.ID_LOTE),
          DESCRIPCIÓN_BIEN: formMatriz.DESCRIPCIÓN_BIEN,
          UNIDAD_MEDIDA: formMatriz.UNIDAD_MEDIDA,
          CANTIDAD_REGULAR: qty,
          OFERTA_1: 0,
          OFERTA_2: 0,
          OFERTA_3: 0,
          VALOR_UNITARIO_PROMEDIO: price,
          VALOR_TORAL_PROMEDIO: qty * price
        };
        onUpdateMatrizItems([...matrizItems, newMat]);
        addAuditLog("INSERTAR", "MATRIZ_ITEM", `Agregado bien '${newMat.DESCRIPCIÓN_BIEN}' a Matriz`);
        setFormMatriz({ ID_LOTE: "", DESCRIPCIÓN_BIEN: "", UNIDAD_MEDIDA: "Unidad", CANTIDAD_REGULAR: "1", VALOR_UNITARIO_PROMEDIO: "1000" });
        break;
      case "NECESIDAD":
        if (!formNecesidad.ID_MATRIZ) return;
        const reg = Number(formNecesidad.CANTIDAD_REGULAR) || 0;
        const vul = Number(formNecesidad.CANTIDAD_VULNERABLE) || 0;
        const med = Number(formNecesidad.CANTIDAD_MEDIA_TECNICA) || 0;
        const fic = Number(formNecesidad.CANTIDAD_FIC) || 0;
        const camp = Number(formNecesidad.CANTIDAD_CAMPESINA_COMPLEMENTARIA) || 0;
        const sum = reg + vul + med + fic + camp;
        const newNec: Necesidad = {
          ID_NECESIDAD: newId,
          ID_MATRIZ: Number(formNecesidad.ID_MATRIZ),
          CANTIDAD_REGULAR: reg,
          CANTIDAD_VULNERABLE: vul,
          CANTIDAD_MEDIA_TECNICA: med,
          CANTIDAD_FIC: fic,
          CANTIDAD_CAMPESINA_COMPLEMENTARIA: camp,
          CANTIDAD_CAMPESINA_TITULADA: 0,
          CANTIDAD_ECONOMIA_POPULAR: 0,
          CANTIDAD_ENI: 0,
          CANTIDAD_FC_CAMPESINA: 0,
          CANTIDAD_NESECIDAD: sum
        };
        onUpdateNecesidades([...necesidades, newNec]);
        addAuditLog("INSERTAR", "NECESIDAD", `Distribuida necesidad de ID_MATRIZ: ${newNec.ID_MATRIZ} con total: ${sum}`);
        setFormNecesidad({ ID_MATRIZ: "", CANTIDAD_REGULAR: "1", CANTIDAD_VULNERABLE: "0", CANTIDAD_MEDIA_TECNICA: "0", CANTIDAD_FIC: "0", CANTIDAD_CAMPESINA_COMPLEMENTARIA: "0" });
        break;
      case "CODIGO_UNSPSC":
        if (!formUnspsc.ID_MATRIZ_ITEM || !formUnspsc.SEGMENTO) return;
        const newUns: CodigoUnspsc = {
          ID_CODIGO: newId,
          ID_MATRIZ_ITEM: Number(formUnspsc.ID_MATRIZ_ITEM),
          SEGMENTO: formUnspsc.SEGMENTO,
          FAMILIA: formUnspsc.FAMILIA,
          CLASE: formUnspsc.CLASE
        };
        onUpdateCodigosUnspsc([...codigosUnspsc, newUns]);
        addAuditLog("INSERTAR", "CODIGO_UNSPSC", `Asociado UNSPSC para Matriz Item: ${newUns.ID_MATRIZ_ITEM}`);
        setFormUnspsc({ ID_MATRIZ_ITEM: "", SEGMENTO: "", FAMILIA: "", CLASE: "" });
        break;
      case "PROVEEDOR":
        if (!formProveedor.NIT || !formProveedor.RAZON_SOCIAL) return;
        const newProv: Proveedor = {
          ID_PROVEEDOR: newId,
          NIT: formProveedor.NIT,
          RAZON_SOCIAL: formProveedor.RAZON_SOCIAL,
          EMAIL: formProveedor.EMAIL
        };
        onUpdateProveedores([...proveedores, newProv]);
        addAuditLog("INSERTAR", "PROVEEDOR", `Creado proveedor '${newProv.RAZON_SOCIAL}'`);
        setFormProveedor({ NIT: "", RAZON_SOCIAL: "", EMAIL: "" });
        break;
      case "CERTIFICADO_EXISTENCIA":
        if (!formCertificado.NUMERO_CERTIFICADO || !formCertificado.ID_LOTE) return;
        const newCert: CertificadoExistencia = {
          ID_CERTIFICADO: newId,
          NUMERO_CERTIFICADO: formCertificado.NUMERO_CERTIFICADO,
          ID_LOTE: Number(formCertificado.ID_LOTE),
          COMENTARIOS: formCertificado.COMENTARIOS,
          FECHA_EMISIÓN: new Date().toISOString()
        };
        onUpdateCertificados([...certificados, newCert]);
        addAuditLog("INSERTAR", "CERTIFICADO_EXISTENCIA", `Expedido Certificado ${newCert.NUMERO_CERTIFICADO}`);
        setFormCertificado({ NUMERO_CERTIFICADO: "", ID_LOTE: "", COMENTARIOS: "" });
        break;
      case "COTIZACION":
        if (!formCotizacion.ID_MATRIZ_ITEM || !formCotizacion.ID_PROVEEDOR || !formCotizacion.VALOR_UNITARIO) return;
        const mat = matrizItems.find(m => m.ID_MATRIZ_ITEM === Number(formCotizacion.ID_MATRIZ_ITEM));
        const finalQty = mat ? mat.CANTIDAD_REGULAR : 1;
        const unitVal = Number(formCotizacion.VALOR_UNITARIO);
        const newCot: Cotizacion = {
          ID_COTIZACION: newId,
          ID_MATRIZ_ITEM: Number(formCotizacion.ID_MATRIZ_ITEM),
          ID_PROVEEDOR: Number(formCotizacion.ID_PROVEEDOR),
          VALOR_UNITARIO: unitVal,
          VALOR_TOTAL: unitVal * finalQty
        };
        onUpdateCotizaciones([...cotizaciones, newCot]);
        addAuditLog("INSERTAR", "COTIZACION", `Cargada cotización por $${unitVal} para Item: ${newCot.ID_MATRIZ_ITEM}`);
        setFormCotizacion({ ID_MATRIZ_ITEM: "", ID_PROVEEDOR: "", VALOR_UNITARIO: "" });
        break;
      case "IVA_COTIZACION":
        if (!formIva.ID_COTIZACON) return;
        const cot = cotizaciones.find(c => c.ID_COTIZACION === Number(formIva.ID_COTIZACON));
        const valUnit = cot ? cot.VALOR_UNITARIO : 0;
        const ivPct = Number(formIva.PERCENTAJE_IVA) / 100;
        const newIva: IvaCotizacion = {
          ID_IVA: newId,
          ID_COTIZACON: Number(formIva.ID_COTIZACON),
          PERCENTAJE_IVA: Number(formIva.PERCENTAJE_IVA),
          VALOR_IVA_UNITARIO: Math.round(valUnit * ivPct),
          NUMERO_OFERTA: formIva.NUMERO_OFERTA
        };
        onUpdateIvas([...ivas, newIva]);
        addAuditLog("INSERTAR", "IVA_COTIZACION", `Desglosado IVA (${formIva.PERCENTAJE_IVA}%) para Cotización ID: ${newIva.ID_COTIZACON}`);
        setFormIva({ ID_COTIZACON: "", PERCENTAJE_IVA: "19", NUMERO_OFERTA: "OFERTA_1" });
        break;
      case "FICHA_TECNICA":
        if (!formFicha.NOMBRE_ITEM || !formFicha.CODIGO_UNSPSC) return;
        const newFic: FichaTecnica = {
          ID_FICHA_TECNICA: newId,
          NOMBRE_ITEM: formFicha.NOMBRE_ITEM,
          CODIGO_UNSPSC: formFicha.CODIGO_UNSPSC,
          DENOMINACIÓN_TECNICA_BIEN: formFicha.DENOMINACIÓN_TECNICA_BIEN,
          UNIDAD_MEDIDA: formFicha.UNIDAD_MEDIDA,
          DESCRIPCIÓN_GENERAL: formFicha.DESCRIPCIÓN_GENERAL,
          MARCA_OFRECIDA: formFicha.MARCA_OFRECIDA,
          FIRMA_PROPONENTE: formFicha.FIRMA_PROPONENTE
        };
        onUpdateFichasTecnicas([...fichasTecnicas, newFic]);
        addAuditLog("INSERTAR", "FICHA_TECNICA", `Estructurada Ficha Técnica de '${newFic.NOMBRE_ITEM}'`);
        setFormFicha({ NOMBRE_ITEM: "", CODIGO_UNSPSC: "", DENOMINACIÓN_TECNICA_BIEN: "", UNIDAD_MEDIDA: "Unidad", DESCRIPCIÓN_GENERAL: "", MARCA_OFRECIDA: "", FIRMA_PROPONENTE: "" });
        break;
    }

    setShowAddForm(false);
  };

  // Helper to fetch table rows based on state
  const getTableData = (): any[] => {
    switch (selectedTable) {
      case "ROL": return roles;
      case "USUARIO": return usuarios;
      case "LOTE_REQUERIMIENTO": return lotes;
      case "MATRIZ_ITEM": return matrizItems;
      case "NECESIDAD": return necesidades;
      case "CODIGO_UNSPSC": return codigosUnspsc;
      case "PROVEEDOR": return proveedores;
      case "CERTIFICADO_EXISTENCIA": return certificados;
      case "COTIZACION": return cotizaciones;
      case "IVA_COTIZACION": return ivas;
      case "FICHA_TECNICA": return fichasTecnicas;
      case "AUDITORIA": return auditLogs;
      default: return [];
    }
  };

  const getTableColumns = (): string[] => {
    const data = getTableData();
    if (data.length === 0) {
      // Return fallback headers based on model schema
      switch (selectedTable) {
        case "ROL": return ["ID_ROL", "NOMBRE_ROL"];
        case "USUARIO": return ["ID_USUARIO", "ID_ROL", "DOCUMENTO", "NOMBRE", "APELLIDO", "EMAIL", "ESTADO"];
        case "LOTE_REQUERIMIENTO": return ["ID_LOTE", "ID_SOLICITANTE", "ID_INSTRUCTOR_APOYO", "LOTE_NOMBRE", "ESTADO_TRAMITE", "FECHA_CREACIÓN"];
        case "MATRIZ_ITEM": return ["ID_MATRIZ_ITEM", "ID_LOTE", "DESCRIPCIÓN_BIEN", "UNIDAD_MEDIDA", "CANTIDAD_REGULAR", "VALOR_UNITARIO_PROMEDIO", "VALOR_TORAL_PROMEDIO"];
        case "NECESIDAD": return ["ID_NECESIDAD", "ID_MATRIZ", "CANTIDAD_REGULAR", "CANTIDAD_VULNERABLE", "CANTIDAD_MEDIA_TECNICA", "CANTIDAD_FIC", "CANTIDAD_CAMPESINA_COMPLEMENTARIA", "CANTIDAD_NESECIDAD"];
        case "CODIGO_UNSPSC": return ["ID_CODIGO", "ID_MATRIZ_ITEM", "SEGMENTO", "FAMILIA", "CLASE"];
        case "PROVEEDOR": return ["ID_PROVEEDOR", "NIT", "RAZON_SOCIAL", "EMAIL"];
        case "CERTIFICADO_EXISTENCIA": return ["ID_CERTIFICADO", "NUMERO_CERTIFICADO", "ID_LOTE", "COMENTARIOS", "FECHA_EMISIÓN"];
        case "COTIZACION": return ["ID_COTIZACION", "ID_MATRIZ_ITEM", "ID_PROVEEDOR", "VALOR_UNITARIO", "VALOR_TOTAL"];
        case "IVA_COTIZACION": return ["ID_IVA", "ID_COTIZACON", "PERCENTAJE_IVA", "VALOR_IVA_UNITARIO", "NUMERO_OFERTA"];
        case "FICHA_TECNICA": return ["ID_FICHA_TECNICA", "NOMBRE_ITEM", "CODIGO_UNSPSC", "DENOMINACIÓN_TECNICA_BIEN", "UNIDAD_MEDIDA", "DESCRIPCIÓN_GENERAL", "MARCA_OFRECIDA", "FIRMA_PROPONENTE"];
        case "AUDITORIA": return ["id", "usuario", "accion", "tabla", "detalle", "fecha"];
        default: return [];
      }
    }
    return Object.keys(data[0]);
  };

  // Filter rows based on search
  const filteredRows = getTableData().filter((row) => {
    if (!searchQuery) return true;
    return Object.values(row).some((val) => 
      String(val).toLowerCase().includes(searchQuery.toLowerCase())
    );
  });

  const activeTableInfo = tablesList.find(t => t.id === selectedTable);

  const copyToClipboard = () => {
    navigator.clipboard.writeText(JSON.stringify(getTableData(), null, 2));
    setCopied(true);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <div id="database-explorer-container" className="space-y-6 animate-fadeIn">
      {/* Intro Header */}
      <div className="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
        <div className="flex items-center gap-3 pb-4 border-b border-slate-100">
          <div className="p-3 bg-[#39A900]/10 text-[#39A900] rounded-xl">
            <Database className="h-6 w-6" />
          </div>
          <div>
            <h2 className="text-xl font-bold text-gray-900">Consola de Base de Datos Relacional (12 Tablas)</h2>
            <p className="text-xs text-slate-500 mt-0.5">
              Inspecciona de forma directa y en tiempo real el modelo lógico del sistema de pre-compra SENA. Agrega o elimina tuplas directamente para forzar estados en el ciclo del trámite.
            </p>
          </div>
        </div>

        {/* Mobile dropdown selector */}
        <div className="block lg:hidden mt-4">
          <label className="block text-[10px] font-bold uppercase text-slate-400 mb-1">Seleccionar Tabla</label>
          <select 
            value={selectedTable} 
            onChange={(e) => { setSelectedTable(e.target.value); setShowAddForm(false); setSearchQuery(""); }}
            className="w-full text-xs font-bold p-2.5 bg-slate-50 border border-slate-200 rounded-xl"
          >
            {tablesList.map((t) => (
              <option key={t.id} value={t.id}>{t.name} ({t.count} reg)</option>
            ))}
          </select>
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Left tables sidebar selector */}
        <div className="hidden lg:block space-y-2 col-span-1">
          <p className="text-[10px] font-black uppercase text-slate-400 tracking-widest px-2 mb-3 font-mono">12 TABLAS RELACIONALES</p>
          <div className="space-y-1 bg-white p-2.5 rounded-2xl border border-slate-200 shadow-3xs max-h-[580px] overflow-y-auto">
            {tablesList.map((tab) => {
              const isActive = selectedTable === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => { setSelectedTable(tab.id); setShowAddForm(false); setSearchQuery(""); }}
                  className={`w-full flex items-center justify-between text-left p-3 rounded-xl transition-all cursor-pointer ${
                    isActive 
                      ? "bg-[#39A900]/10 text-[#39A900] font-black border-l-4 border-[#39A900] pl-2.5" 
                      : "text-slate-600 hover:bg-slate-50 hover:text-slate-950 font-medium"
                  }`}
                >
                  <div className="truncate pr-1">
                    <span className="text-xs block truncate">{tab.name}</span>
                    <span className="text-[9px] text-slate-400 font-mono font-normal block truncate">{tab.desc.substring(0, 36)}...</span>
                  </div>
                  <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-mono ${isActive ? "bg-[#39A900] text-white" : "bg-slate-100 text-slate-600"}`}>
                    {tab.count}
                  </span>
                </button>
              );
            })}
          </div>
        </div>

        {/* Right workspace view */}
        <div className="lg:col-span-3 space-y-6">
          {/* Active Table Details Metadata Banner */}
          {activeTableInfo && (
            <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-3xs relative overflow-hidden">
              <div className="absolute top-0 right-0 w-24 h-24 bg-slate-50 rounded-full translate-x-8 -translate-y-8 -z-10 flex items-center justify-center">
                <Database className="w-12 h-12 text-slate-100" />
              </div>
              
              <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                  <span className="text-[9px] bg-slate-100 text-slate-600 font-bold px-2 py-0.5 rounded font-mono uppercase">
                    TABLA FISICA: {activeTableInfo.id}
                  </span>
                  <h3 className="text-base font-black text-slate-900 mt-1">{activeTableInfo.name.split(". ")[1]}</h3>
                  <p className="text-xs text-slate-500 mt-1">{activeTableInfo.desc}</p>
                </div>

                <div className="flex gap-2">
                  <button
                    onClick={copyToClipboard}
                    className="inline-flex items-center gap-1.5 text-slate-600 hover:text-slate-950 bg-slate-100 hover:bg-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold transition-all cursor-pointer"
                    title="Copiar toda la tabla como JSON"
                  >
                    {copied ? <Check className="w-3.5 h-3.5 text-emerald-600" /> : <Copy className="w-3.5 h-3.5" />}
                    <span>{copied ? "¡Copiado!" : "Copiar JSON"}</span>
                  </button>

                  {selectedTable !== "AUDITORIA" && (
                    <button
                      onClick={() => setShowAddForm(!showAddForm)}
                      className="inline-flex items-center gap-1.5 text-white bg-[#39A900] hover:bg-[#2e8800] px-3.5 py-1.5 rounded-lg text-xs font-bold transition-all shadow-sm cursor-pointer"
                    >
                      <Plus className="w-3.5 h-3.5" />
                      <span>{showAddForm ? "Cancelar Form" : "Agregar Registro"}</span>
                    </button>
                  )}
                </div>
              </div>

              {/* Relationship helper box */}
              <div className="mt-3 bg-slate-50 p-2.5 rounded-xl border border-slate-150 text-[10px] text-slate-500 flex items-center gap-4 flex-wrap">
                <div>
                  <span className="font-bold text-slate-700">Llave Primaria (PK):</span> <code className="bg-white px-1 py-0.5 border border-slate-200 rounded text-slate-600 font-mono">{activeTableInfo.pk}</code>
                </div>
                {activeTableInfo.fk && (
                  <div>
                    <span className="font-bold text-slate-700">Llave Foránea (FK):</span> <code className="bg-white px-1 py-0.5 border border-slate-200 rounded text-[#39A900] font-mono">{activeTableInfo.fk}</code>
                  </div>
                )}
                <div className="ml-auto flex items-center gap-1">
                  <Info className="w-3 h-3 text-[#39A900]" />
                  <span>Sincronización de llaves e integridad relacional simulada</span>
                </div>
              </div>
            </div>
          )}

          {/* Add Row Form container (Dynamic based on selectedTable) */}
          {showAddForm && (
            <div className="bg-white rounded-2xl p-5 border border-[#39A900]/30 shadow-md animate-fadeIn">
              <h4 className="text-xs font-black text-slate-900 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                <Plus className="w-4 h-4 text-[#39A900]" />
                Insertar Fila en TBL_{selectedTable}
              </h4>

              <form onSubmit={handleAddRow} className="space-y-4">
                
                {selectedTable === "ROL" && (
                  <div className="grid grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">ID_ROL (Manual)</label>
                      <input 
                        type="number" required placeholder="Ej: 5" 
                        value={formRol.ID_ROL} onChange={e => setFormRol({...formRol, ID_ROL: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-[#39A900]"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">NOMBRE_ROL</label>
                      <select
                        value={formRol.NOMBRE_ROL} onChange={e => setFormRol({...formRol, NOMBRE_ROL: e.target.value as RolNombre})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-[#39A900]"
                      >
                        {Object.values(RolNombre).map(r => <option key={r} value={r}>{r}</option>)}
                      </select>
                    </div>
                  </div>
                )}

                {selectedTable === "USUARIO" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Documento</label>
                      <input 
                        type="text" required placeholder="71234567" 
                        value={formUsuario.DOCUMENTO} onChange={e => setFormUsuario({...formUsuario, DOCUMENTO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Nombre</label>
                      <input 
                        type="text" required placeholder="Carlos" 
                        value={formUsuario.NOMBRE} onChange={e => setFormUsuario({...formUsuario, NOMBRE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Apellido</label>
                      <input 
                        type="text" required placeholder="Gomez" 
                        value={formUsuario.APELLIDO} onChange={e => setFormUsuario({...formUsuario, APELLIDO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Email</label>
                      <input 
                        type="email" required placeholder="carlos@sena.edu.co" 
                        value={formUsuario.EMAIL} onChange={e => setFormUsuario({...formUsuario, EMAIL: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg font-mono"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Rol ID (FK_ROL)</label>
                      <select
                        value={formUsuario.ID_ROL} onChange={e => setFormUsuario({...formUsuario, ID_ROL: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        {roles.map(r => <option key={r.ID_ROL} value={r.ID_ROL}>{r.NOMBRE_ROL} (ID: {r.ID_ROL})</option>)}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Estado</label>
                      <select
                        value={formUsuario.ESTADO} onChange={e => setFormUsuario({...formUsuario, ESTADO: e.target.value as "ACTIVO" | "INACTIVO"})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="ACTIVO">ACTIVO</option>
                        <option value="INACTIVO">INACTIVO</option>
                      </select>
                    </div>
                  </div>
                )}

                {selectedTable === "LOTE_REQUERIMIENTO" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div className="sm:col-span-2">
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Nombre del Lote</label>
                      <input 
                        type="text" required placeholder="Lote de Insumos Metalmecánicos T3" 
                        value={formLote.LOTE_NAME} onChange={e => setFormLote({...formLote, LOTE_NAME: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Instructor Lider (FK_SOLICITANTE)</label>
                      <select
                        value={formLote.ID_SOLICITANTE} onChange={e => setFormLote({...formLote, ID_SOLICITANTE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        {usuarios.filter(u => u.ID_ROL === 1).map(u => (
                          <option key={u.ID_USUARIO} value={u.ID_USUARIO}>{u.NOMBRE} {u.APELLIDO} (ID: {u.ID_USUARIO})</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Instructor Apoyo (FK)</label>
                      <select
                        value={formLote.ID_INSTRUCTOR_APOYO} onChange={e => setFormLote({...formLote, ID_INSTRUCTOR_APOYO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        {usuarios.filter(u => u.ID_ROL === 1).map(u => (
                          <option key={u.ID_USUARIO} value={u.ID_USUARIO}>{u.NOMBRE} {u.APELLIDO} (ID: {u.ID_USUARIO})</option>
                        ))}
                      </select>
                    </div>
                  </div>
                )}

                {selectedTable === "MATRIZ_ITEM" && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Lote Asociado (FK_LOTE)</label>
                      <select
                        required value={formMatriz.ID_LOTE} onChange={e => setFormMatriz({...formMatriz, ID_LOTE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Lote --</option>
                        {lotes.map(l => (
                          <option key={l.ID_LOTE} value={l.ID_LOTE}>{l.LOTE_NOMBRE.substring(0, 30)}... (ID: {l.ID_LOTE})</option>
                        ))}
                      </select>
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Descripción del Bien</label>
                      <input 
                        type="text" required placeholder="Destornillador de Estrella Profesional" 
                        value={formMatriz.DESCRIPCIÓN_BIEN} onChange={e => setFormMatriz({...formMatriz, DESCRIPCIÓN_BIEN: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Unidad Medida</label>
                      <input 
                        type="text" required placeholder="Unidad" 
                        value={formMatriz.UNIDAD_MEDIDA} onChange={e => setFormMatriz({...formMatriz, UNIDAD_MEDIDA: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Cantidad Regular</label>
                      <input 
                        type="number" required min="1" 
                        value={formMatriz.CANTIDAD_REGULAR} onChange={e => setFormMatriz({...formMatriz, CANTIDAD_REGULAR: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Valor Unitario Promedio</label>
                      <input 
                        type="number" required min="0" placeholder="COP"
                        value={formMatriz.VALOR_UNITARIO_PROMEDIO} onChange={e => setFormMatriz({...formMatriz, VALOR_UNITARIO_PROMEDIO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                  </div>
                )}

                {selectedTable === "NECESIDAD" && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Matriz Item (FK_MATRIZ)</label>
                      <select
                        required value={formNecesidad.ID_MATRIZ} onChange={e => setFormNecesidad({...formNecesidad, ID_MATRIZ: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Bien --</option>
                        {matrizItems.map(m => (
                          <option key={m.ID_MATRIZ_ITEM} value={m.ID_MATRIZ_ITEM}>{m.DESCRIPCIÓN_BIEN.substring(0, 30)}... (ID: {m.ID_MATRIZ_ITEM})</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Regular</label>
                      <input 
                        type="number" value={formNecesidad.CANTIDAD_REGULAR} onChange={e => setFormNecesidad({...formNecesidad, CANTIDAD_REGULAR: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Vulnerable</label>
                      <input 
                        type="number" value={formNecesidad.CANTIDAD_VULNERABLE} onChange={e => setFormNecesidad({...formNecesidad, CANTIDAD_VULNERABLE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Media Técnica</label>
                      <input 
                        type="number" value={formNecesidad.CANTIDAD_MEDIA_TECNICA} onChange={e => setFormNecesidad({...formNecesidad, CANTIDAD_MEDIA_TECNICA: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">FIC (Construcción)</label>
                      <input 
                        type="number" value={formNecesidad.CANTIDAD_FIC} onChange={e => setFormNecesidad({...formNecesidad, CANTIDAD_FIC: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Campesina</label>
                      <input 
                        type="number" value={formNecesidad.CANTIDAD_CAMPESINA_COMPLEMENTARY} onChange={e => setFormNecesidad({...formNecesidad, CANTIDAD_CAMPESINA_COMPLEMENTARIA: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                  </div>
                )}

                {selectedTable === "CODIGO_UNSPSC" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Matriz Item (FK)</label>
                      <select
                        required value={formUnspsc.ID_MATRIZ_ITEM} onChange={e => setFormUnspsc({...formUnspsc, ID_MATRIZ_ITEM: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Bien --</option>
                        {matrizItems.map(m => (
                          <option key={m.ID_MATRIZ_ITEM} value={m.ID_MATRIZ_ITEM}>{m.DESCRIPCIÓN_BIEN.substring(0, 30)}... (ID: {m.ID_MATRIZ_ITEM})</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Segmento</label>
                      <input 
                        type="text" required placeholder="43 (Tecnología de la Información)" 
                        value={formUnspsc.SEGMENTO} onChange={e => setFormUnspsc({...formUnspsc, SEGMENTO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Familia</label>
                      <input 
                        type="text" required placeholder="4321 (Computadores)" 
                        value={formUnspsc.FAMILIA} onChange={e => setFormUnspsc({...formUnspsc, FAMILIA: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Clase</label>
                      <input 
                        type="text" required placeholder="432115 (Portátiles)" 
                        value={formUnspsc.CLASE} onChange={e => setFormUnspsc({...formUnspsc, CLASE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                  </div>
                )}

                {selectedTable === "PROVEEDOR" && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">NIT</label>
                      <input 
                        type="text" required placeholder="900.555.123-1" 
                        value={formProveedor.NIT} onChange={e => setFormProveedor({...formProveedor, NIT: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Razon Social</label>
                      <input 
                        type="text" required placeholder="Ferretería Central S.A." 
                        value={formProveedor.RAZON_SOCIAL} onChange={e => setFormProveedor({...formProveedor, RAZON_SOCIAL: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Email Contacto</label>
                      <input 
                        type="email" required placeholder="contacto@empresa.com" 
                        value={formProveedor.EMAIL} onChange={e => setFormProveedor({...formProveedor, EMAIL: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg font-mono"
                      />
                    </div>
                  </div>
                )}

                {selectedTable === "CERTIFICADO_EXISTENCIA" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Número Certificado</label>
                      <input 
                        type="text" required placeholder="CNE-2026-0999" 
                        value={formCertificado.NUMERO_CERTIFICADO} onChange={e => setFormCertificado({...formCertificado, NUMERO_CERTIFICADO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg font-mono"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Lote Asociado (FK_LOTE)</label>
                      <select
                        required value={formCertificado.ID_LOTE} onChange={e => setFormCertificado({...formCertificado, ID_LOTE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Lote --</option>
                        {lotes.map(l => (
                          <option key={l.ID_LOTE} value={l.ID_LOTE}>{l.LOTE_NOMBRE.substring(0, 30)}... (ID: {l.ID_LOTE})</option>
                        ))}
                      </select>
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Comentarios de Validación de Stock</label>
                      <textarea 
                        required placeholder="Físicamente se certifica que no hay existencias en ninguna bodega..." 
                        value={formCertificado.COMENTARIOS} onChange={e => setFormCertificado({...formCertificado, COMENTARIOS: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg h-16"
                      />
                    </div>
                  </div>
                )}

                {selectedTable === "COTIZACION" && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Matriz Item (FK)</label>
                      <select
                        required value={formCotizacion.ID_MATRIZ_ITEM} onChange={e => setFormCotizacion({...formCotizacion, ID_MATRIZ_ITEM: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Bien --</option>
                        {matrizItems.map(m => (
                          <option key={m.ID_MATRIZ_ITEM} value={m.ID_MATRIZ_ITEM}>{m.DESCRIPCIÓN_BIEN.substring(0, 30)}... (ID: {m.ID_MATRIZ_ITEM})</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Proveedor (FK)</label>
                      <select
                        required value={formCotizacion.ID_PROVEEDOR} onChange={e => setFormCotizacion({...formCotizacion, ID_PROVEEDOR: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Proveedor --</option>
                        {proveedores.map(p => (
                          <option key={p.ID_PROVEEDOR} value={p.ID_PROVEEDOR}>{p.RAZON_SOCIAL} (ID: {p.ID_PROVEEDOR})</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Valor Unitario Oferta (COP)</label>
                      <input 
                        type="number" required placeholder="50000" 
                        value={formCotizacion.VALOR_UNITARIO} onChange={e => setFormCotizacion({...formCotizacion, VALOR_UNITARIO: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                  </div>
                )}

                {selectedTable === "IVA_COTIZACION" && (
                  <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Cotización Asociada (FK)</label>
                      <select
                        required value={formIva.ID_COTIZACON} onChange={e => setFormIva({...formIva, ID_COTIZACON: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="">-- Seleccionar Oferta --</option>
                        {cotizaciones.map(c => (
                          <option key={c.ID_COTIZACION} value={c.ID_COTIZACION}>ID Cotización: {c.ID_COTIZACION} (${c.VALOR_UNITARIO})</option>
                        ))}
                      </select>
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Porcentaje IVA (%)</label>
                      <input 
                        type="number" required placeholder="19" min="0" max="100" 
                        value={formIva.PERCENTAJE_IVA} onChange={e => setFormIva({...formIva, PERCENTAJE_IVA: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Tipo Oferta</label>
                      <select
                        value={formIva.NUMERO_OFERTA} onChange={e => setFormIva({...formIva, NUMERO_OFERTA: e.target.value as any})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      >
                        <option value="OFERTA_1">OFERTA 1</option>
                        <option value="OFERTA_2">OFERTA 2</option>
                        <option value="OFERTA_3">OFERTA 3</option>
                      </select>
                    </div>
                  </div>
                )}

                {selectedTable === "FICHA_TECNICA" && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Nombre Ítem</label>
                      <input 
                        type="text" required placeholder="Ej: Taladro Percutor Inalámbrico" 
                        value={formFicha.NOMBRE_ITEM} onChange={e => setFormFicha({...formFicha, NOMBRE_ITEM: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Código UNSPSC</label>
                      <input 
                        type="text" required placeholder="43232101" 
                        value={formFicha.CODIGO_UNSPSC} onChange={e => setFormFicha({...formFicha, CODIGO_UNSPSC: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg font-mono"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Denominación Técnica Comercial</label>
                      <input 
                        type="text" required placeholder="Taladro de Impacto 20V Motor Brushless" 
                        value={formFicha.DENOMINACIÓN_TECNICA_BIEN} onChange={e => setFormFicha({...formFicha, DENOMINACIÓN_TECNICA_BIEN: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div>
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Marca Ofrecida</label>
                      <input 
                        type="text" required placeholder="DeWalt / Bosch / Makita" 
                        value={formFicha.MARCA_OFRECIDA} onChange={e => setFormFicha({...formFicha, MARCA_OFRECIDA: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Descripción de Características Técnicas de Ficha</label>
                      <textarea 
                        required placeholder="Especificaciones detalladas de potencia, revoluciones, accesorios incluidos..." 
                        value={formFicha.DESCRIPCIÓN_GENERAL} onChange={e => setFormFicha({...formFicha, DESCRIPCIÓN_GENERAL: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg h-16"
                      />
                    </div>
                    <div className="sm:col-span-2">
                      <label className="block text-[10px] font-bold text-slate-400 uppercase">Firma del Proponente Autorizado</label>
                      <input 
                        type="text" required placeholder="Nombre del Representante Legal, Cargo, Empresa" 
                        value={formFicha.FIRMA_PROPONENTE} onChange={e => setFormFicha({...formFicha, FIRMA_PROPONENTE: e.target.value})}
                        className="w-full text-xs p-2 bg-slate-50 border border-slate-200 rounded-lg"
                      />
                    </div>
                  </div>
                )}

                <div className="flex justify-end gap-2.5 pt-2">
                  <button
                    type="button" onClick={() => setShowAddForm(false)}
                    className="bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold px-4 py-2 rounded-xl text-xs transition-all cursor-pointer"
                  >
                    Cerrar Formulario
                  </button>
                  <button
                    type="submit"
                    className="bg-[#39A900] hover:bg-[#2e8800] text-white font-extrabold px-5 py-2 rounded-xl text-xs transition-all shadow-sm cursor-pointer"
                  >
                    Guardar Fila en Caliente
                  </button>
                </div>

              </form>
            </div>
          )}

          {/* Table Data View Grid Card */}
          <div className="bg-white rounded-2xl shadow-3xs border border-slate-200 overflow-hidden">
            {/* Search filter bar */}
            <div className="p-4 bg-slate-50 border-b border-slate-200 flex flex-col sm:flex-row items-center gap-3 justify-between">
              <div className="relative w-full sm:max-w-xs">
                <span className="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                  <Search className="w-4 h-4" />
                </span>
                <input
                  type="text"
                  placeholder="Buscar en esta tabla..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  className="w-full text-xs pl-9 pr-3 py-2 bg-white border border-slate-200 rounded-xl focus:outline-none focus:ring-1 focus:ring-[#39A900] focus:border-[#39A900]"
                />
              </div>

              <div className="text-[11px] text-slate-400 font-mono">
                Filtrados: <span className="font-bold text-slate-800">{filteredRows.length}</span> de <span className="font-bold text-slate-600">{getTableData().length}</span> registros
              </div>
            </div>

            {/* Main Table render block */}
            <div className="overflow-x-auto">
              {filteredRows.length === 0 ? (
                <div className="p-12 text-center text-slate-400">
                  <ShieldAlert className="w-10 h-10 mx-auto text-slate-300 mb-3" />
                  <p className="text-xs font-bold text-slate-500">No se encontraron tuplas o registros matching</p>
                  <p className="text-[10px] text-slate-400 mt-1">Intente cambiar el parámetro de búsqueda o agregue una fila de prueba.</p>
                </div>
              ) : (
                <table className="w-full text-left border-collapse text-xs">
                  <thead>
                    <tr className="bg-slate-50 border-b border-slate-200 text-slate-400 font-extrabold uppercase tracking-wider select-none">
                      {getTableColumns().map((col) => (
                        <th key={col} className="p-3 font-bold font-mono text-[10px]">{col}</th>
                      ))}
                      {selectedTable !== "AUDITORIA" && <th className="p-3 text-center text-[10px]">Acciones</th>}
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-slate-100 font-medium">
                    {filteredRows.map((row, idx) => {
                      const columns = getTableColumns();
                      const pkField = activeTableInfo?.pk || "id";
                      const pkValue = row[pkField];

                      return (
                        <tr key={idx} className="hover:bg-slate-50/50 transition-colors">
                          {columns.map((col) => {
                            const val = row[col];
                            // Beautiful representation of values
                            let displayVal = String(val);
                            if (col === "ESTADO" || col === "ESTADO_TRAMITE") {
                              const badgeStyle = 
                                val === "BORRADOR" ? "bg-amber-50 text-amber-600 border-amber-200" :
                                val === "ENVIADO_A_COORDINADOR" ? "bg-blue-50 text-blue-600 border-blue-200" :
                                val === "APROBADO_COORDINADOR" ? "bg-purple-50 text-purple-600 border-purple-200" :
                                val === "CON_CERTIFICADO_NO_EXISTENCIA" ? "bg-orange-50 text-orange-600 border-orange-200" :
                                val === "COTIZADO" ? "bg-emerald-50 text-emerald-600 border-emerald-200" :
                                val === "ACTIVO" ? "bg-emerald-50 text-emerald-600 border-emerald-200" :
                                "bg-slate-50 text-slate-500 border-slate-200";

                              return (
                                <td key={col} className="p-3">
                                  <span className={`px-2 py-0.5 rounded text-[10px] font-bold border ${badgeStyle}`}>
                                    {val}
                                  </span>
                                </td>
                              );
                            }

                            if (col.includes("VALOR") || col.includes("OFERTA") || col === "VALOR_TOTAL") {
                              return (
                                <td key={col} className="p-3 font-bold font-mono text-slate-800">
                                  {Number(val) > 0 ? `$${Number(val).toLocaleString()}` : "$0"}
                                </td>
                              );
                            }

                            if (col.includes("ID_") || col === "id") {
                              return (
                                <td key={col} className="p-3 font-mono font-bold text-[#39A900]">
                                  {val}
                                </td>
                              );
                            }

                            return (
                              <td key={col} className="p-3 text-slate-700 max-w-[200px] truncate" title={displayVal}>
                                {displayVal}
                              </td>
                            );
                          })}

                          {selectedTable !== "AUDITORIA" && (
                            <td className="p-3 text-center">
                              <button
                                onClick={() => handleDeleteRow(pkField, pkValue)}
                                className="p-1.5 text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg transition-all cursor-pointer"
                                title="Eliminar registro de la base de datos"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            </td>
                          )}
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              )}
            </div>

          </div>

          {/* Database Info Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
              <h4 className="text-xs font-bold text-slate-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                <Info className="w-4 h-4 text-[#39A900]" />
                Integridad Relacional y Cascade
              </h4>
              <p className="text-xs text-slate-500 leading-relaxed">
                Este explorador de bases de datos simula la integridad referencial. Si eliminas un ítem en la tabla <b>MATRIZ_ITEM</b>, debes verificar que las cotizaciones y las clasificaciones <b>UNSPSC</b> correspondientes sean depuradas para mantener la congruencia comercial en los estudios de mercado.
              </p>
            </div>

            <div className="bg-white rounded-2xl p-5 border border-slate-200 shadow-2xs">
              <h4 className="text-xs font-bold text-slate-800 uppercase tracking-widest mb-3 flex items-center gap-1.5">
                <Filter className="w-4 h-4 text-emerald-600" />
                Reglas de Negocio en Caliente
              </h4>
              <p className="text-xs text-slate-500 leading-relaxed">
                Puedes cambiar el estado de los lotes de requerimiento directamente o crear nuevos usuarios para iniciar sesión con otras credenciales personalizadas. La sincronización se realiza en el hilo principal guardando automáticamente cada tupla en el navegador.
              </p>
            </div>
          </div>

        </div>
      </div>
    </div>
  );
}
