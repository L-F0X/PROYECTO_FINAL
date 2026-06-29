export enum RolNombre {
  INSTRUCTOR = "INSTRUCTOR",
  COORDINADOR = "COORDINADOR",
  ALMACENISTA = "ALMACENISTA",
  PROVEEDOR = "PROVEEDOR"
}

export interface Rol {
  ID_ROL: number;
  NOMBRE_ROL: RolNombre;
}

export interface Usuario {
  ID_USUARIO: number;
  ID_ROL: number;
  DOCUMENTO: string;
  NOMBRE: string;
  APELLIDO: string;
  EMAIL: string;
  PASSWORD?: string;
  ESTADO: "ACTIVO" | "INACTIVO";
}

export interface Solicitante {
  ID_INSTRUCTOR_LIDER: number;
  ID_INSTRUCTOR_APOYO: number;
}

export type EstadoTramite = 
  | "BORRADOR" 
  | "ENVIADO_A_COORDINADOR" 
  | "RECHAZADO_COORDINADOR" 
  | "APROBADO_COORDINADOR" 
  | "CON_CERTIFICADO_NO_EXISTENCIA" 
  | "COTIZADO" 
  | "PROCESADO";

export interface LoteRequerimiento {
  ID_LOTE: number;
  ID_SOLICITANTE: number; // ID_INSTRUCTOR_LIDER
  ID_INSTRUCTOR_APOYO?: number;
  LOTE_NOMBRE: string;
  ESTADO_TRAMITE: EstadoTramite;
  FECHA_CREACIÓN: string;
  COMENTARIOS_COORDINADOR?: string;
}

export interface Proveedor {
  ID_PROVEEDOR: number;
  NIT: string;
  RAZON_SOCIAL: string;
  EMAIL: string;
}

export interface CertificadoExistencia {
  ID_CERTIFICADO: number;
  NUMERO_CERTIFICADO: string;
  ID_LOTE: number;
  COMENTARIOS: string;
  FECHA_EMISIÓN: string;
}

export interface Necesidad {
  ID_NECESIDAD: number;
  ID_MATRIZ: number; // FK to MatrizItem
  CANTIDAD_REGULAR: number;
  CANTIDAD_CAMPESINA_COMPLEMENTARIA: number;
  CANTIDAD_CAMPESINA_TITULADA: number;
  CANTIDAD_VULNERABLE: number;
  CANTIDAD_MEDIA_TECNICA: number;
  CANTIDAD_FIC: number;
  CANTIDAD_ECONOMIA_POPULAR: number;
  CANTIDAD_ENI: number;
  CANTIDAD_FC_CAMPESINA: number;
  CANTIDAD_NESECIDAD: number; // Total quantity calculated
}

export interface MatrizItem {
  ID_MATRIZ_ITEM: number;
  ID_LOTE: number;
  DESCRIPCIÓN_BIEN: string;
  UNIDAD_MEDIDA: string;
  CANTIDAD_REGULAR: number; // Quantity
  OFERTA_1: number;
  OFERTA_2: number;
  OFERTA_3: number;
  VALOR_UNITARIO_PROMEDIO: number;
  VALOR_TORAL_PROMEDIO: number;
}

export interface CodigoUnspsc {
  ID_CODIGO: number;
  ID_MATRIZ_ITEM: number;
  SEGMENTO: string;
  FAMILIA: string;
  CLASE: string;
}

export interface Cotizacion {
  ID_COTIZACION: number;
  ID_MATRIZ_ITEM: number;
  ID_PROVEEDOR: number;
  VALOR_UNITARIO: number;
  VALOR_TOTAL: number;
}

export interface IvaCotizacion {
  ID_IVA: number;
  ID_COTIZACON: number; // FK to Cotizacion
  PERCENTAJE_IVA: number;
  VALOR_IVA_UNITARIO: number;
  NUMERO_OFERTA: "OFERTA_1" | "OFERTA_2" | "OFERTA_3";
}

export interface FichaTecnica {
  ID_FICHA_TECNICA: number;
  NOMBRE_ITEM: string; // references MatrizItem.DESCRIPCIÓN_BIEN
  CODIGO_UNSPSC: string;
  DENOMINACIÓN_TECNICA_BIEN: string;
  UNIDAD_MEDIDA: string;
  DESCRIPCIÓN_GENERAL: string;
  MARCA_OFRECIDA: string;
  FIRMA_PROPONENTE: string;
}
