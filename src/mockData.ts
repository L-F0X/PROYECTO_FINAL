import { 
  RolNombre, 
  Usuario, 
  LoteRequerimiento, 
  MatrizItem, 
  Necesidad, 
  CodigoUnspsc, 
  Proveedor, 
  CertificadoExistencia, 
  Cotizacion, 
  IvaCotizacion, 
  FichaTecnica 
} from "./types";

// Standard SENA roles mapping
export const mockRoles = [
  { ID_ROL: 1, NOMBRE_ROL: RolNombre.INSTRUCTOR },
  { ID_ROL: 2, NOMBRE_ROL: RolNombre.COORDINADOR },
  { ID_ROL: 3, NOMBRE_ROL: RolNombre.ALMACENISTA },
  { ID_ROL: 4, NOMBRE_ROL: RolNombre.PROVEEDOR }
];

// Mock Users
export const mockUsuarios: Usuario[] = [
  {
    ID_USUARIO: 101,
    ID_ROL: 1,
    DOCUMENTO: "80123456",
    NOMBRE: "Carlos",
    APELLIDO: "Gómez",
    EMAIL: "carlos.gomez@sena.edu.co",
    ESTADO: "ACTIVO"
  },
  {
    ID_USUARIO: 102,
    ID_ROL: 1,
    DOCUMENTO: "10154321",
    NOMBRE: "María Teresa",
    APELLIDO: "Rodríguez",
    EMAIL: "mt.rodriguez@sena.edu.co",
    ESTADO: "ACTIVO"
  },
  {
    ID_USUARIO: 201,
    ID_ROL: 2,
    DOCUMENTO: "52456789",
    NOMBRE: "Esperanza",
    APELLIDO: "Castro",
    EMAIL: "ecastrom@sena.edu.co",
    ESTADO: "ACTIVO"
  },
  {
    ID_USUARIO: 301,
    ID_ROL: 3,
    DOCUMENTO: "79111222",
    NOMBRE: "Humberto",
    APELLIDO: "López",
    EMAIL: "hlopez@sena.edu.co",
    ESTADO: "ACTIVO"
  },
  {
    ID_USUARIO: 401,
    ID_ROL: 4,
    DOCUMENTO: "NIT-900123456-1",
    NOMBRE: "TecnoSuministros",
    APELLIDO: "S.A.S.",
    EMAIL: "ventas@tecnosuministros.com",
    ESTADO: "ACTIVO"
  },
  {
    ID_USUARIO: 402,
    ID_ROL: 4,
    DOCUMENTO: "NIT-890456123-2",
    NOMBRE: "Dotaciones",
    APELLIDO: "Industriales del Caribe",
    EMAIL: "licitaciones@dotacaribe.com",
    ESTADO: "ACTIVO"
  }
];

// Mock Proveedores
export const mockProveedores: Proveedor[] = [
  {
    ID_PROVEEDOR: 401,
    NIT: "900.123.456-1",
    RAZON_SOCIAL: "TecnoSuministros S.A.S.",
    EMAIL: "ventas@tecnosuministros.com"
  },
  {
    ID_PROVEEDOR: 402,
    NIT: "890.456.123-2",
    RAZON_SOCIAL: "Dotaciones Industriales del Caribe",
    EMAIL: "licitaciones@dotacaribe.com"
  }
];

// Preloaded Lotes de Requerimiento
export const mockLotes: LoteRequerimiento[] = [
  {
    ID_LOTE: 1,
    ID_SOLICITANTE: 101,
    ID_INSTRUCTOR_APOYO: 102,
    LOTE_NOMBRE: "Lote de Materiales de Gastronomía - Cocina Regional T2",
    ESTADO_TRAMITE: "BORRADOR",
    FECHA_CREACIÓN: "2026-06-15"
  },
  {
    ID_LOTE: 2,
    ID_SOLICITANTE: 101,
    ID_INSTRUCTOR_APOYO: 102,
    LOTE_NOMBRE: "Lote de Herramientas Eléctricas para Mantenimiento Industrial",
    ESTADO_TRAMITE: "ENVIADO_A_COORDINADOR",
    FECHA_CREACIÓN: "2026-06-20"
  },
  {
    ID_LOTE: 3,
    ID_SOLICITANTE: 102,
    ID_INSTRUCTOR_APOYO: 101,
    LOTE_NOMBRE: "Equipo de Protección Personal (EPP) y Salud Ocupacional",
    ESTADO_TRAMITE: "APROBADO_COORDINADOR",
    FECHA_CREACIÓN: "2026-06-22",
    COMENTARIOS_COORDINADOR: "Aprobado por viabilidad técnica y presupuestal. Pasar a almacén para validación de stock."
  },
  {
    ID_LOTE: 4,
    ID_SOLICITANTE: 101,
    ID_INSTRUCTOR_APOYO: 102,
    LOTE_NOMBRE: "Software de Diseño CAD y Licencias de Animación 3D",
    ESTADO_TRAMITE: "CON_CERTIFICADO_NO_EXISTENCIA",
    FECHA_CREACIÓN: "2026-06-23",
    COMENTARIOS_COORDINADOR: "Suficiente justificación para el programa de ADSO."
  },
  {
    ID_LOTE: 5,
    ID_SOLICITANTE: 102,
    ID_INSTRUCTOR_APOYO: 101,
    LOTE_NOMBRE: "Kits de Electrónica y Robótica para Automatización",
    ESTADO_TRAMITE: "COTIZADO",
    FECHA_CREACIÓN: "2026-06-10",
    COMENTARIOS_COORDINADOR: "Requerido para semillero de investigación SENA Nova."
  }
];

// Preloaded Certificados
export const mockCertificados: CertificadoExistencia[] = [
  {
    ID_CERTIFICADO: 3001,
    NUMERO_CERTIFICADO: "CNE-2026-0089",
    ID_LOTE: 4,
    COMENTARIOS: "Se certifica que en el inventario del centro no se cuenta con licencias activas disponibles de CAD, proceda con pre-compra.",
    FECHA_EMISIÓN: "2026-06-24T14:30:00"
  },
  {
    ID_CERTIFICADO: 3002,
    NUMERO_CERTIFICADO: "CNE-2026-0095",
    ID_LOTE: 5,
    COMENTARIOS: "Validado el stock físico de almacén. No hay existencias de microcontroladores ni servomotores con las especificaciones requeridas.",
    FECHA_EMISIÓN: "2026-06-12T09:15:00"
  }
];

// Matriz Items
export const mockMatrizItems: MatrizItem[] = [
  // Lote 1 (Gastronomía - Borrador)
  {
    ID_MATRIZ_ITEM: 1001,
    ID_LOTE: 1,
    DESCRIPCIÓN_BIEN: "Harina de Trigo Fortificada Especial",
    UNIDAD_MEDIDA: "Kilogramos",
    CANTIDAD_REGULAR: 120,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 4500,
    VALOR_TORAL_PROMEDIO: 540000
  },
  {
    ID_MATRIZ_ITEM: 1002,
    ID_LOTE: 1,
    DESCRIPCIÓN_BIEN: "Cuchillo de Cocina Profesional de 8 pulgadas",
    UNIDAD_MEDIDA: "Unidad",
    CANTIDAD_REGULAR: 15,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 85000,
    VALOR_TORAL_PROMEDIO: 1275000
  },
  // Lote 2 (Herramientas Eléctricas - Enviado a Coordinador)
  {
    ID_MATRIZ_ITEM: 2001,
    ID_LOTE: 2,
    DESCRIPCIÓN_BIEN: "Taladro Percutor Inalámbrico de 20V Profesional",
    UNIDAD_MEDIDA: "Unidad",
    CANTIDAD_REGULAR: 12,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 450000,
    VALOR_TORAL_PROMEDIO: 5400000
  },
  {
    ID_MATRIZ_ITEM: 2002,
    ID_LOTE: 2,
    DESCRIPCIÓN_BIEN: "Esmeriladora Angular de 4-1/2 pulgadas 850W",
    UNIDAD_MEDIDA: "Unidad",
    CANTIDAD_REGULAR: 8,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 220000,
    VALOR_TORAL_PROMEDIO: 1760000
  },
  // Lote 3 (EPP - Aprobado Coordinador, Esperando Almacenista)
  {
    ID_MATRIZ_ITEM: 3001,
    ID_LOTE: 3,
    DESCRIPCIÓN_BIEN: "Guantes de Nitrilo de Alta Resistencia (Caja x 100)",
    UNIDAD_MEDIDA: "Caja",
    CANTIDAD_REGULAR: 50,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 42000,
    VALOR_TORAL_PROMEDIO: 2100000
  },
  {
    ID_MATRIZ_ITEM: 3002,
    ID_LOTE: 3,
    DESCRIPCIÓN_BIEN: "Gafas de Seguridad con Protección UV y Antiempañantes",
    UNIDAD_MEDIDA: "Unidad",
    CANTIDAD_REGULAR: 100,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 12500,
    VALOR_TORAL_PROMEDIO: 1250000
  },
  // Lote 4 (Software - Con Certificado, Esperando Cotizaciones)
  {
    ID_MATRIZ_ITEM: 4001,
    ID_LOTE: 4,
    DESCRIPCIÓN_BIEN: "Licencias de Autodesk AutoCAD Anuales (Suscripción Educativa)",
    UNIDAD_MEDIDA: "Licencia",
    CANTIDAD_REGULAR: 30,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 1850000,
    VALOR_TORAL_PROMEDIO: 55500000
  },
  {
    ID_MATRIZ_ITEM: 4002,
    ID_LOTE: 4,
    DESCRIPCIÓN_BIEN: "Licencias Adobe Creative Cloud Completa Anual",
    UNIDAD_MEDIDA: "Licencia",
    CANTIDAD_REGULAR: 25,
    OFERTA_1: 0,
    OFERTA_2: 0,
    OFERTA_3: 0,
    VALOR_UNITARIO_PROMEDIO: 1200000,
    VALOR_TORAL_PROMEDIO: 30000000
  },
  // Lote 5 (Robótica - Cotizado)
  {
    ID_MATRIZ_ITEM: 5001,
    ID_LOTE: 5,
    DESCRIPCIÓN_BIEN: "Placa de Desarrollo Arduino Uno R3 Original",
    UNIDAD_MEDIDA: "Unidad",
    CANTIDAD_REGULAR: 40,
    OFERTA_1: 105000,
    OFERTA_2: 112000,
    OFERTA_3: 98000,
    VALOR_UNITARIO_PROMEDIO: 105000,
    VALOR_TORAL_PROMEDIO: 4200000
  },
  {
    ID_MATRIZ_ITEM: 5002,
    ID_LOTE: 5,
    DESCRIPCIÓN_BIEN: "Servomotor SG90 Micro 9g",
    UNIDAD_MEDIDA: "Unidad",
    CANTIDAD_REGULAR: 80,
    OFERTA_1: 12000,
    OFERTA_2: 14000,
    OFERTA_3: 11500,
    VALOR_UNITARIO_PROMEDIO: 12500,
    VALOR_TORAL_PROMEDIO: 1000000
  }
];

// Necesidades - Details of quantities for different populations/programs
export const mockNecesidades: Necesidad[] = [
  // Harina de Trigo (Item 1001)
  {
    ID_NECESIDAD: 50001,
    ID_MATRIZ: 1001,
    CANTIDAD_REGULAR: 50,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 20,
    CANTIDAD_CAMPESINA_TITULADA: 10,
    CANTIDAD_VULNERABLE: 15,
    CANTIDAD_MEDIA_TECNICA: 5,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 20,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 120
  },
  // Cuchillos (Item 1002)
  {
    ID_NECESIDAD: 50002,
    ID_MATRIZ: 1002,
    CANTIDAD_REGULAR: 10,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
    CANTIDAD_CAMPESINA_TITULADA: 0,
    CANTIDAD_VULNERABLE: 3,
    CANTIDAD_MEDIA_TECNICA: 0,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 2,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 15
  },
  // Taladros (Item 2001)
  {
    ID_NECESIDAD: 50003,
    ID_MATRIZ: 2001,
    CANTIDAD_REGULAR: 6,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 2,
    CANTIDAD_CAMPESINA_TITULADA: 0,
    CANTIDAD_VULNERABLE: 2,
    CANTIDAD_MEDIA_TECNICA: 2,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 12
  },
  // Esmeriladora (Item 2002)
  {
    ID_NECESIDAD: 50004,
    ID_MATRIZ: 2002,
    CANTIDAD_REGULAR: 4,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
    CANTIDAD_CAMPESINA_TITULADA: 0,
    CANTIDAD_VULNERABLE: 2,
    CANTIDAD_MEDIA_TECNICA: 1,
    CANTIDAD_FIC: 1,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 8
  },
  // Guantes Nitrilo (Item 3001)
  {
    ID_NECESIDAD: 50005,
    ID_MATRIZ: 3001,
    CANTIDAD_REGULAR: 20,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 10,
    CANTIDAD_CAMPESINA_TITULADA: 5,
    CANTIDAD_VULNERABLE: 5,
    CANTIDAD_MEDIA_TECNICA: 5,
    CANTIDAD_FIC: 5,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 50
  },
  // Gafas Seguridad (Item 3002)
  {
    ID_NECESIDAD: 50006,
    ID_MATRIZ: 3002,
    CANTIDAD_REGULAR: 50,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 10,
    CANTIDAD_CAMPESINA_TITULADA: 10,
    CANTIDAD_VULNERABLE: 10,
    CANTIDAD_MEDIA_TECNICA: 10,
    CANTIDAD_FIC: 10,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 100
  },
  // Licencias AutoCAD (Item 4001)
  {
    ID_NECESIDAD: 50007,
    ID_MATRIZ: 4001,
    CANTIDAD_REGULAR: 20,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
    CANTIDAD_CAMPESINA_TITULADA: 0,
    CANTIDAD_VULNERABLE: 5,
    CANTIDAD_MEDIA_TECNICA: 5,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 30
  },
  // Licencias Adobe Creative (Item 4002)
  {
    ID_NECESIDAD: 50008,
    ID_MATRIZ: 4002,
    CANTIDAD_REGULAR: 15,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 0,
    CANTIDAD_CAMPESINA_TITULADA: 0,
    CANTIDAD_VULNERABLE: 5,
    CANTIDAD_MEDIA_TECNICA: 5,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 25
  },
  // Placas Arduino (Item 5001)
  {
    ID_NECESIDAD: 50009,
    ID_MATRIZ: 5001,
    CANTIDAD_REGULAR: 20,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 5,
    CANTIDAD_CAMPESINA_TITULADA: 5,
    CANTIDAD_VULNERABLE: 5,
    CANTIDAD_MEDIA_TECNICA: 5,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 40
  },
  // Servomotor (Item 5002)
  {
    ID_NECESIDAD: 50010,
    ID_MATRIZ: 5002,
    CANTIDAD_REGULAR: 40,
    CANTIDAD_CAMPESINA_COMPLEMENTARIA: 10,
    CANTIDAD_CAMPESINA_TITULADA: 10,
    CANTIDAD_VULNERABLE: 10,
    CANTIDAD_MEDIA_TECNICA: 10,
    CANTIDAD_FIC: 0,
    CANTIDAD_ECONOMIA_POPULAR: 0,
    CANTIDAD_ENI: 0,
    CANTIDAD_FC_CAMPESINA: 0,
    CANTIDAD_NESECIDAD: 80
  }
];

// UNSPSC Code mappings (from the PDF "CODIGO_UNSPSC" and "FICHA_TECNICA" details)
export const mockCodigosUnspsc: CodigoUnspsc[] = [
  {
    ID_CODIGO: 80001,
    ID_MATRIZ_ITEM: 1001,
    SEGMENTO: "50 (Alimentos, Bebidas y Tabaco)",
    FAMILIA: "5050 (Harinas, Granos y Derivados)",
    CLASE: "505022 (Harinas de cereales)"
  },
  {
    ID_CODIGO: 80002,
    ID_MATRIZ_ITEM: 1002,
    SEGMENTO: "52 (Artículos Domésticos y de Consumo)",
    FAMILIA: "5215 (Utensilios de Cocina)",
    CLASE: "521515 (Cuchillería)"
  },
  {
    ID_CODIGO: 80003,
    ID_MATRIZ_ITEM: 2001,
    SEGMENTO: "27 (Herramientas y Maquinaria)",
    FAMILIA: "2711 (Herramientas de mano)",
    CLASE: "271127 (Herramientas motorizadas/Taladros)"
  },
  {
    ID_CODIGO: 80004,
    ID_MATRIZ_ITEM: 2002,
    SEGMENTO: "27 (Herramientas y Maquinaria)",
    FAMILIA: "2711 (Herramientas de mano)",
    CLASE: "271119 (Esmeriladoras)"
  },
  {
    ID_CODIGO: 80005,
    ID_MATRIZ_ITEM: 3001,
    SEGMENTO: "46 (Equipos y Suministros de Defensa/Seguridad)",
    FAMILIA: "4618 (Equipo de protección personal)",
    CLASE: "461815 (Protección de manos/Guantes)"
  },
  {
    ID_CODIGO: 80006,
    ID_MATRIZ_ITEM: 3002,
    SEGMENTO: "46 (Equipos y Suministros de Defensa/Seguridad)",
    FAMILIA: "4618 (Equipo de protección personal)",
    CLASE: "461818 (Protección de ojos/Gafas)"
  },
  {
    ID_CODIGO: 80007,
    ID_MATRIZ_ITEM: 4001,
    SEGMENTO: "43 (Tecnología de la Información y Telecomunicaciones)",
    FAMILIA: "4323 (Software de computación)",
    CLASE: "432321 (Software de Diseño asistido por computador CAD)"
  },
  {
    ID_CODIGO: 80008,
    ID_MATRIZ_ITEM: 4002,
    SEGMENTO: "43 (Tecnología de la Información y Telecomunicaciones)",
    FAMILIA: "4323 (Software de computación)",
    CLASE: "432324 (Software de diseño gráfico y edición)"
  },
  {
    ID_CODIGO: 80009,
    ID_MATRIZ_ITEM: 5001,
    SEGMENTO: "32 (Componentes Electrónicos)",
    FAMILIA: "3212 (Circuitos activos e integrados)",
    CLASE: "321216 (Microcontroladores)"
  },
  {
    ID_CODIGO: 80010,
    ID_MATRIZ_ITEM: 5002,
    SEGMENTO: "26 (Maquinaria y Accesorios de Generación/Distribución de Energía)",
    FAMILIA: "2610 (Motores)",
    CLASE: "261016 (Servomotores)"
  }
];

// Preloaded Cotizaciones (from Suppliers)
export const mockCotizaciones: Cotizacion[] = [
  // TecnoSuministros SAS quotes for Lote 5
  {
    ID_COTIZACION: 90001,
    ID_MATRIZ_ITEM: 5001,
    ID_PROVEEDOR: 401,
    VALOR_UNITARIO: 105000,
    VALOR_TOTAL: 4200000 // 105k * 40
  },
  {
    ID_COTIZACION: 90002,
    ID_MATRIZ_ITEM: 5002,
    ID_PROVEEDOR: 401,
    VALOR_UNITARIO: 12000,
    VALOR_TOTAL: 960000 // 12k * 80
  },
  // Dotaciones Industriales del Caribe quotes for Lote 5
  {
    ID_COTIZACION: 90003,
    ID_MATRIZ_ITEM: 5001,
    ID_PROVEEDOR: 402,
    VALOR_UNITARIO: 112000,
    VALOR_TOTAL: 4480000 // 112k * 40
  },
  {
    ID_COTIZACION: 90004,
    ID_MATRIZ_ITEM: 5002,
    ID_PROVEEDOR: 402,
    VALOR_UNITARIO: 11500,
    VALOR_TOTAL: 920000 // 11.5k * 80
  }
];

// Preloaded IVA
export const mockIvas: IvaCotizacion[] = [
  {
    ID_IVA: 95001,
    ID_COTIZACON: 90001,
    PERCENTAJE_IVA: 19,
    VALOR_IVA_UNITARIO: 19950, // 105k * 19%
    NUMERO_OFERTA: "OFERTA_1"
  },
  {
    ID_IVA: 95002,
    ID_COTIZACON: 90002,
    PERCENTAJE_IVA: 19,
    VALOR_IVA_UNITARIO: 2280, // 12k * 19%
    NUMERO_OFERTA: "OFERTA_1"
  },
  {
    ID_IVA: 95003,
    ID_COTIZACON: 90003,
    PERCENTAJE_IVA: 19,
    VALOR_IVA_UNITARIO: 21280, // 112k * 19%
    NUMERO_OFERTA: "OFERTA_2"
  },
  {
    ID_IVA: 95004,
    ID_COTIZACON: 90004,
    PERCENTAJE_IVA: 19,
    VALOR_IVA_UNITARIO: 2185, // 11.5k * 19%
    NUMERO_OFERTA: "OFERTA_3"
  }
];

// Preloaded Fichas Técnicas
export const mockFichasTecnicas: FichaTecnica[] = [
  {
    ID_FICHA_TECNICA: 70001,
    NOMBRE_ITEM: "Licencias de Autodesk AutoCAD Anuales (Suscripción Educativa)",
    CODIGO_UNSPSC: "43232101",
    DENOMINACIÓN_TECNICA_BIEN: "Suscripción Anual de Software de Diseño AutoCAD para Centros de Formación",
    UNIDAD_MEDIDA: "Licencia",
    DESCRIPCIÓN_GENERAL: "Suscripción académica anual multiusuario con soporte cloud oficial de Autodesk. Permite el modelado 2D y 3D avanzado y exportación de archivos vectoriales.",
    MARCA_OFRECIDA: "Autodesk",
    FIRMA_PROPONENTE: "Ing. Alejandro Torres, Director de Tecnología en TecnoSuministros SAS"
  },
  {
    ID_FICHA_TECNICA: 70002,
    NOMBRE_ITEM: "Placa de Desarrollo Arduino Uno R3 Original",
    CODIGO_UNSPSC: "32121603",
    DENOMINACIÓN_TECNICA_BIEN: "Placa microcontroladora basada en el chip ATmega328P, interfaz USB, caja oficial",
    UNIDAD_MEDIDA: "Unidad",
    DESCRIPCIÓN_GENERAL: "Microcontrolador ATmega328P de 8 bits, voltaje operativo de 5V, 14 pines de E/S digitales (6 PWM), 6 entradas analógicas, velocidad de reloj de 16 MHz.",
    MARCA_OFRECIDA: "Arduino SRL Original",
    FIRMA_PROPONENTE: "Ing. Alejandro Torres, Director de Tecnología en TecnoSuministros SAS"
  }
];

// LocalStorage helpers to load and save states
export const loadFromStorage = <T>(key: string, defaultValue: T): T => {
  try {
    const saved = localStorage.getItem(key);
    if (saved) {
      return JSON.parse(saved);
    }
  } catch (error) {
    console.error("Error loading key from localStorage", key, error);
  }
  return defaultValue;
};

export const saveToStorage = <T>(key: string, value: T): void => {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch (error) {
    console.error("Error saving key to localStorage", key, error);
  }
};

// Reset all storage to original mock data
export const clearAllStorage = (): void => {
  localStorage.removeItem("sena_lotes");
  localStorage.removeItem("sena_matriz_items");
  localStorage.removeItem("sena_necesidades");
  localStorage.removeItem("sena_certificados");
  localStorage.removeItem("sena_cotizaciones");
  localStorage.removeItem("sena_ivas");
  localStorage.removeItem("sena_fichas_tecnicas");
  window.location.reload();
};
