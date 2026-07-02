-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 01-07-2026 a las 17:14:17
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `bicergam`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `certificado_existencia`
--

CREATE TABLE `certificado_existencia` (
  `ID_CERTIFICADO` int(11) NOT NULL,
  `ID_LOTE` int(11) NOT NULL,
  `NUMERO_CERTIFICADO` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `codigo_unspsc`
--

CREATE TABLE `codigo_unspsc` (
  `ID_CODIGO` int(11) NOT NULL,
  `SEGMENTO` varchar(50) DEFAULT NULL,
  `FAMILIA` varchar(50) DEFAULT NULL,
  `CLASE` varchar(50) DEFAULT NULL,
  `CODIGO_UNSPSC` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `codigo_unspsc`
--

INSERT INTO `codigo_unspsc` (`ID_CODIGO`, `SEGMENTO`, `FAMILIA`, `CLASE`, `CODIGO_UNSPSC`) VALUES
(1, 'SIN', 'ASIG', 'CL', 'SIN_ASIGNAR'),
(2, 'SIN', 'ASIG', 'CL', '451278'),
(3, 'SIN', 'ASIG', 'CL', '451279');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion`
--

CREATE TABLE `cotizacion` (
  `ID_COTIZACION` int(11) NOT NULL,
  `ID_MATRIZ_ITEM` int(11) NOT NULL,
  `ID_PROVEEDOR` int(11) NOT NULL,
  `ID_IVA` int(11) NOT NULL,
  `VALOR_UNITARIO` int(11) NOT NULL,
  `VALOR_TOTAL` int(11) NOT NULL,
  `MARCA_OFRECIDA` varchar(100) DEFAULT NULL,
  `FIRMA_PROPONENTE` varchar(250) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Disparadores `cotizacion`
--
DELIMITER $$
CREATE TRIGGER `TR_CALCULAR_TOTAL_COTIZACION` BEFORE INSERT ON `cotizacion` FOR EACH ROW BEGIN
    DECLARE v_cantidad INT;
    DECLARE v_porcentaje_iva DECIMAL(5,2);
    DECLARE v_neto_total INT;

    -- 1. Obtener la cantidad total requerida para este ítem desde la matriz
    SELECT CANTIDAD_REGULAR INTO v_cantidad 
    FROM MATRIZ_ITEM 
    WHERE ID_MATRIZ_ITEM = NEW.ID_MATRIZ_ITEM;

    -- 2. Obtener el porcentaje de IVA asignado a esta cotización
    SELECT PORCENTAJE INTO v_porcentaje_iva 
    FROM IVA 
    WHERE ID_IVA = NEW.ID_IVA;

    -- 3. Calcular el valor neto total (Cantidad * Valor Unitario)
    SET v_neto_total = v_cantidad * NEW.VALOR_UNITARIO;

    -- 4. Asignar automáticamente al campo VALOR_TOTAL el neto más el IVA correspondiente
    SET NEW.VALOR_TOTAL = ROUND(v_neto_total * (1 + (v_porcentaje_iva / 100)), 0);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ficha_tecnica`
--

CREATE TABLE `ficha_tecnica` (
  `ID_FICHA_TECNICA` int(11) NOT NULL,
  `ID_MATRIZ_ITEM` int(11) DEFAULT NULL,
  `NOMBRE_ITEM` varchar(150) NOT NULL,
  `CODIGO_UNSPSC_FK` varchar(20) DEFAULT NULL,
  `DENOMINACION_TECNICA_BIEN` text NOT NULL,
  `UNIDAD_MEDIDA` varchar(50) NOT NULL,
  `CANTIDAD` int(255) NOT NULL,
  `DESCRIPCION_GENERAL` text DEFAULT NULL,
  `COMENTARIOS` text DEFAULT NULL,
  `FECHA_EMISION` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `ficha_tecnica`
--

INSERT INTO `ficha_tecnica` (`ID_FICHA_TECNICA`, `ID_MATRIZ_ITEM`, `NOMBRE_ITEM`, `CODIGO_UNSPSC_FK`, `DENOMINACION_TECNICA_BIEN`, `UNIDAD_MEDIDA`, `CANTIDAD`, `DESCRIPCION_GENERAL`, `COMENTARIOS`, `FECHA_EMISION`) VALUES
(1, NULL, 'esponja', '451278', 'esponja de vasos', 'unidad', 0, 'limpieza esponja para lavar y brillar vasos', '4*5', '2026-07-01 14:38:57'),
(2, 7, 'esponja', '451279', 'esponja de ollas', 'unidad', 0, 'ollas limpia todo arranca grasa', 'metalicas', '2026-07-01 15:10:19');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `iva`
--

CREATE TABLE `iva` (
  `ID_IVA` int(11) NOT NULL,
  `PORCENTAJE` decimal(5,2) NOT NULL,
  `DESCRIPCION` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `iva`
--

INSERT INTO `iva` (`ID_IVA`, `PORCENTAJE`, `DESCRIPCION`) VALUES
(1, 0.00, '');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `lote_requerimiento`
--

CREATE TABLE `lote_requerimiento` (
  `ID_LOTE` int(11) NOT NULL,
  `ID_SOLICITANTE` int(11) NOT NULL,
  `LOTE_NOMBRE` varchar(100) NOT NULL,
  `ESTADO_TRAMITE` enum('Borrador','Enviado','Aprobado','Rechazado') NOT NULL,
  `FECHA_CREACION` date NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `lote_requerimiento`
--

INSERT INTO `lote_requerimiento` (`ID_LOTE`, `ID_SOLICITANTE`, `LOTE_NOMBRE`, `ESTADO_TRAMITE`, `FECHA_CREACION`) VALUES
(2, 1, 'REDES', 'Borrador', '2026-06-29'),
(4, 1, 'Computo', 'Borrador', '2026-07-01'),
(5, 1, 'Componentes hardware', 'Borrador', '2026-07-01');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `matriz_item`
--

CREATE TABLE `matriz_item` (
  `ID_MATRIZ_ITEM` int(11) NOT NULL,
  `ID_LOTE` int(11) NOT NULL,
  `ID_NECESIDAD` int(11) DEFAULT NULL,
  `ID_CODIGO_UNSPSC` int(11) NOT NULL,
  `ID_IVA` int(11) NOT NULL,
  `DESCRIPCION_BIEN` text NOT NULL,
  `UNIDAD_MEDIDA` varchar(50) NOT NULL,
  `CANTIDAD_REGULAR` int(11) NOT NULL DEFAULT 0,
  `OFERTA_1` int(11) DEFAULT NULL,
  `OFERTA_2` int(11) DEFAULT NULL,
  `OFERTA_3` int(11) DEFAULT NULL,
  `VALOR_UNITARIO_PROMEDIO` int(11) DEFAULT NULL,
  `VALOR_TOTAL_PROMEDIO` int(11) DEFAULT NULL,
  `FICHA_TECNICA` text DEFAULT NULL,
  `ESTADO_ITEM` varchar(30) NOT NULL DEFAULT 'Borrador',
  `INSTRUCTOR_APOYO` int(11) DEFAULT NULL,
  `ID_FICHA_TECNICA` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `matriz_item`
--

INSERT INTO `matriz_item` (`ID_MATRIZ_ITEM`, `ID_LOTE`, `ID_NECESIDAD`, `ID_CODIGO_UNSPSC`, `ID_IVA`, `DESCRIPCION_BIEN`, `UNIDAD_MEDIDA`, `CANTIDAD_REGULAR`, `OFERTA_1`, `OFERTA_2`, `OFERTA_3`, `VALOR_UNITARIO_PROMEDIO`, `VALOR_TOTAL_PROMEDIO`, `FICHA_TECNICA`, `ESTADO_ITEM`, `INSTRUCTOR_APOYO`, `ID_FICHA_TECNICA`) VALUES
(7, 2, NULL, 1, 1, 'Item prueba automatizada 4', '', 5, NULL, NULL, NULL, NULL, NULL, 'Ficha técnica prueba 4', 'Borrador', NULL, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `necesidad`
--

CREATE TABLE `necesidad` (
  `ID_NECESIDAD` int(11) NOT NULL,
  `ID_MATRIZ` int(11) NOT NULL,
  `CANTIDAD_REGULAR` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_CAMPESINA_COMPLEMENTARIA` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_CAMPESINA_TITULADA` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_VULNERABLE` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_MEDIA_TECNICA` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_FIC` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_ECONOMIA_POPULAR` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_ENI` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_FC_CAMPESINA` int(11) NOT NULL DEFAULT 0,
  `CANTIDAD_NESECIDAD` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedor`
--

CREATE TABLE `proveedor` (
  `ID_PROVEEDOR` int(11) NOT NULL,
  `NIT` varchar(20) NOT NULL,
  `RAZON_SOCIAL` varchar(150) NOT NULL,
  `EMAIL` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rol`
--

CREATE TABLE `rol` (
  `ID_ROL` int(11) NOT NULL,
  `NOMBRE_ROL` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `rol`
--

INSERT INTO `rol` (`ID_ROL`, `NOMBRE_ROL`) VALUES
(1, 'Instructor'),
(2, 'Coordinacion'),
(3, 'Almacenista');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitante`
--

CREATE TABLE `solicitante` (
  `ID_INSTRUCTOR_LIDER` int(11) NOT NULL,
  `ID_INSTRUCTOR_APOYO` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `solicitante`
--

INSERT INTO `solicitante` (`ID_INSTRUCTOR_LIDER`, `ID_INSTRUCTOR_APOYO`) VALUES
(1, NULL);

--
-- Disparadores `solicitante`
--
DELIMITER $$
CREATE TRIGGER `TR_VALIDAR_ROLES_SOLICITANTE_INSERT` BEFORE INSERT ON `solicitante` FOR EACH ROW BEGIN
    DECLARE rol_lider INT;
    DECLARE rol_apoyo INT;

    SELECT ID_ROL INTO rol_lider FROM USUARIO WHERE ID_USUARIO = NEW.ID_INSTRUCTOR_LIDER;
    IF rol_lider <> 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: El usuario asignado como Instructor Líder debe tener el rol de Instructor.';
    END IF;

    IF NEW.ID_INSTRUCTOR_APOYO IS NOT NULL THEN
        SELECT ID_ROL INTO rol_apoyo FROM USUARIO WHERE ID_USUARIO = NEW.ID_INSTRUCTOR_APOYO;
        IF rol_apoyo <> 1 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: El usuario asignado como Instructor de Apoyo debe tener el rol de Instructor.';
        END IF;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `TR_VALIDAR_ROLES_SOLICITANTE_UPDATE` BEFORE UPDATE ON `solicitante` FOR EACH ROW BEGIN
    DECLARE rol_lider INT;
    DECLARE rol_apoyo INT;

    SELECT ID_ROL INTO rol_lider FROM USUARIO WHERE ID_USUARIO = NEW.ID_INSTRUCTOR_LIDER;
    IF rol_lider <> 1 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Error: El usuario asignado como Instructor Líder debe tener el rol de Instructor.';
    END IF;

    IF NEW.ID_INSTRUCTOR_APOYO IS NOT NULL THEN
        SELECT ID_ROL INTO rol_apoyo FROM USUARIO WHERE ID_USUARIO = NEW.ID_INSTRUCTOR_APOYO;
        IF rol_apoyo <> 1 THEN
            SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Error: El usuario asignado como Instructor de Apoyo debe tener el rol de Instructor.';
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `ID_USUARIO` int(11) NOT NULL,
  `ID_ROL` int(11) NOT NULL,
  `DOCUMENTO` varchar(20) NOT NULL,
  `NOMBRE` varchar(100) NOT NULL,
  `APELLIDO` varchar(100) NOT NULL,
  `EMAIL` varchar(100) NOT NULL,
  `PASSWORD` text NOT NULL,
  `ESTADO` enum('Activo','Inactivo','Pendiente') DEFAULT 'Activo'
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_spanish2_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`ID_USUARIO`, `ID_ROL`, `DOCUMENTO`, `NOMBRE`, `APELLIDO`, `EMAIL`, `PASSWORD`, `ESTADO`) VALUES
(1, 1, '12345678', 'Carlos', 'Gómez', 'instructor@sena.edu.co', '$2y$10$Prshz7r8TvHIyFKAYA7/2OMFEuExmF2BK89HdFUkxFnYy5fv2yAr.', 'Activo'),
(2, 2, '987654321', 'Marta', 'Lucía Ruiz', 'mruiz@sena.edu.co', '$2y$10$3wR6Nde0747KSsIUcojtoe.Xn8C0KW7kiOXAjJMh5Jic82d5Hgosi', 'Activo'),
(5, 3, '10203040', 'Nombre', 'Almacenista', 'almacenista@sena.edu.co', '$2y$10$R9ycuPg7yo.kDHHzEUEkmuC8hzYeSLSKqP.iHoIpAjSVMGDxxMH1K', 'Activo');

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `certificado_existencia`
--
ALTER TABLE `certificado_existencia`
  ADD PRIMARY KEY (`ID_CERTIFICADO`),
  ADD UNIQUE KEY `UK_CERTIFICADO_NUMERO` (`NUMERO_CERTIFICADO`),
  ADD KEY `FK_CERTIFICADO_LOTE` (`ID_LOTE`);

--
-- Indices de la tabla `codigo_unspsc`
--
ALTER TABLE `codigo_unspsc`
  ADD PRIMARY KEY (`ID_CODIGO`),
  ADD UNIQUE KEY `UK_UNSPSC_CODIGO` (`CODIGO_UNSPSC`);

--
-- Indices de la tabla `cotizacion`
--
ALTER TABLE `cotizacion`
  ADD PRIMARY KEY (`ID_COTIZACION`),
  ADD KEY `FK_COTIZACION_MATRIZ` (`ID_MATRIZ_ITEM`),
  ADD KEY `FK_COTIZACION_PROVEEDOR` (`ID_PROVEEDOR`),
  ADD KEY `FK_COTIZACION_IVA` (`ID_IVA`);

--
-- Indices de la tabla `ficha_tecnica`
--
ALTER TABLE `ficha_tecnica`
  ADD PRIMARY KEY (`ID_FICHA_TECNICA`),
  ADD KEY `FK_FICHA_MATRIZ` (`ID_MATRIZ_ITEM`);

--
-- Indices de la tabla `iva`
--
ALTER TABLE `iva`
  ADD PRIMARY KEY (`ID_IVA`);

--
-- Indices de la tabla `lote_requerimiento`
--
ALTER TABLE `lote_requerimiento`
  ADD PRIMARY KEY (`ID_LOTE`),
  ADD KEY `FK_LOTE_SOLICITANTE` (`ID_SOLICITANTE`);

--
-- Indices de la tabla `matriz_item`
--
ALTER TABLE `matriz_item`
  ADD PRIMARY KEY (`ID_MATRIZ_ITEM`),
  ADD KEY `FK_MATRIZ_LOTE` (`ID_LOTE`),
  ADD KEY `FK_MATRIZ_UNSPSC` (`ID_CODIGO_UNSPSC`),
  ADD KEY `FK_MATRIZ_IVA` (`ID_IVA`);

--
-- Indices de la tabla `necesidad`
--
ALTER TABLE `necesidad`
  ADD PRIMARY KEY (`ID_NECESIDAD`),
  ADD KEY `FK_NECESIDAD_MATRIZ` (`ID_MATRIZ`);

--
-- Indices de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  ADD PRIMARY KEY (`ID_PROVEEDOR`),
  ADD UNIQUE KEY `UK_PROVEEDOR_NIT` (`NIT`);

--
-- Indices de la tabla `rol`
--
ALTER TABLE `rol`
  ADD PRIMARY KEY (`ID_ROL`);

--
-- Indices de la tabla `solicitante`
--
ALTER TABLE `solicitante`
  ADD PRIMARY KEY (`ID_INSTRUCTOR_LIDER`),
  ADD KEY `FK_SOLICITANTE_APOYO` (`ID_INSTRUCTOR_APOYO`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`ID_USUARIO`),
  ADD UNIQUE KEY `UK_USUARIO_DOCUMENTO` (`DOCUMENTO`),
  ADD UNIQUE KEY `UK_USUARIO_EMAIL` (`EMAIL`),
  ADD KEY `FK_USUARIO_ROL` (`ID_ROL`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `certificado_existencia`
--
ALTER TABLE `certificado_existencia`
  MODIFY `ID_CERTIFICADO` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `codigo_unspsc`
--
ALTER TABLE `codigo_unspsc`
  MODIFY `ID_CODIGO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `cotizacion`
--
ALTER TABLE `cotizacion`
  MODIFY `ID_COTIZACION` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `ficha_tecnica`
--
ALTER TABLE `ficha_tecnica`
  MODIFY `ID_FICHA_TECNICA` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `iva`
--
ALTER TABLE `iva`
  MODIFY `ID_IVA` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `lote_requerimiento`
--
ALTER TABLE `lote_requerimiento`
  MODIFY `ID_LOTE` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `matriz_item`
--
ALTER TABLE `matriz_item`
  MODIFY `ID_MATRIZ_ITEM` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT de la tabla `necesidad`
--
ALTER TABLE `necesidad`
  MODIFY `ID_NECESIDAD` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `proveedor`
--
ALTER TABLE `proveedor`
  MODIFY `ID_PROVEEDOR` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rol`
--
ALTER TABLE `rol`
  MODIFY `ID_ROL` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `ID_USUARIO` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `certificado_existencia`
--
ALTER TABLE `certificado_existencia`
  ADD CONSTRAINT `FK_CERTIFICADO_LOTE` FOREIGN KEY (`ID_LOTE`) REFERENCES `lote_requerimiento` (`ID_LOTE`);

--
-- Filtros para la tabla `cotizacion`
--
ALTER TABLE `cotizacion`
  ADD CONSTRAINT `FK_COTIZACION_IVA` FOREIGN KEY (`ID_IVA`) REFERENCES `iva` (`ID_IVA`),
  ADD CONSTRAINT `FK_COTIZACION_MATRIZ` FOREIGN KEY (`ID_MATRIZ_ITEM`) REFERENCES `matriz_item` (`ID_MATRIZ_ITEM`),
  ADD CONSTRAINT `FK_COTIZACION_PROVEEDOR` FOREIGN KEY (`ID_PROVEEDOR`) REFERENCES `proveedor` (`ID_PROVEEDOR`);

--
-- Filtros para la tabla `ficha_tecnica`
--
ALTER TABLE `ficha_tecnica`
  ADD CONSTRAINT `FK_FICHA_MATRIZ` FOREIGN KEY (`ID_MATRIZ_ITEM`) REFERENCES `matriz_item` (`ID_MATRIZ_ITEM`);

--
-- Filtros para la tabla `lote_requerimiento`
--
ALTER TABLE `lote_requerimiento`
  ADD CONSTRAINT `FK_LOTE_SOLICITANTE` FOREIGN KEY (`ID_SOLICITANTE`) REFERENCES `solicitante` (`ID_INSTRUCTOR_LIDER`);

--
-- Filtros para la tabla `matriz_item`
--
ALTER TABLE `matriz_item`
  ADD CONSTRAINT `FK_MATRIZ_IVA` FOREIGN KEY (`ID_IVA`) REFERENCES `iva` (`ID_IVA`),
  ADD CONSTRAINT `FK_MATRIZ_LOTE` FOREIGN KEY (`ID_LOTE`) REFERENCES `lote_requerimiento` (`ID_LOTE`),
  ADD CONSTRAINT `FK_MATRIZ_UNSPSC` FOREIGN KEY (`ID_CODIGO_UNSPSC`) REFERENCES `codigo_unspsc` (`ID_CODIGO`);

--
-- Filtros para la tabla `necesidad`
--
ALTER TABLE `necesidad`
  ADD CONSTRAINT `FK_NECESIDAD_MATRIZ` FOREIGN KEY (`ID_MATRIZ`) REFERENCES `matriz_item` (`ID_MATRIZ_ITEM`);

--
-- Filtros para la tabla `solicitante`
--
ALTER TABLE `solicitante`
  ADD CONSTRAINT `FK_SOLICITANTE_APOYO` FOREIGN KEY (`ID_INSTRUCTOR_APOYO`) REFERENCES `usuario` (`ID_USUARIO`),
  ADD CONSTRAINT `FK_SOLICITANTE_LIDER` FOREIGN KEY (`ID_INSTRUCTOR_LIDER`) REFERENCES `usuario` (`ID_USUARIO`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `FK_USUARIO_ROL` FOREIGN KEY (`ID_ROL`) REFERENCES `rol` (`ID_ROL`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
