<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Spanish language strings for local_reschedule.
 *
 * @package    local_reschedule
 * @copyright  2026 Juan Pablo de Castro
 * @author     Juan Pablo de Castro <juan.pablo.de.castro@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Replanificador de Actividades';
$string['reschedule'] = 'Replanificar fechas';
$string['rescheduletitle'] = 'Replanificación y Línea Temporal del Curso';
$string['rescheduledesc'] = 'Arrastre las actividades o ajuste sus extremos de inicio y fin para sincronizar la secuencia dentro del periodo del curso.';
$string['mapping'] = 'Reglas de mapeo de fechas de actividades';
$string['mapping_desc'] = 'Defina por líneas: nombre de tabla, columna de título, tipo, columna de inicio, columna de fin. Los subtipos o fases deben iniciar con un guion (-). El sexto campo opcional indica la clave foránea en subtotales hijas.';
$string['autosequence'] = 'Auto-secuenciar';
$string['resetschedule'] = 'Restablecer';
$string['unsavedchanges'] = 'Tiene cambios pendientes de guardar.';
$string['schedulesaved'] = 'La programación de actividades se ha guardado correctamente.';
$string['error_saving'] = 'Se produjo un error al guardar la programación.';
$string['error_saving_header'] = 'No se pudo guardar la programación';
$string['error_saving_session'] = 'Su sesión ha caducado. Por favor, recargue la página e inicie sesión de nuevo.';
$string['error_saving_permission'] = 'No dispone de permisos para gestionar el calendario de actividades de este curso.';
$string['error_saving_missing_params'] = 'Faltan parámetros obligatorios (courseid o sesskey).';
$string['error_saving_network'] = 'Error de red o servidor no disponible.';
$string['durationdays'] = '{$a} días';
$string['durationhours'] = '{$a} horas';
$string['noactivitiesfound'] = 'No se encontraron actividades con fechas configuradas en este curso.';
$string['activity'] = 'Actividad';
$string['type'] = 'Tipo';
$string['datestart'] = 'Fecha de inicio';
$string['dateend'] = 'Fecha de fin';
$string['duration'] = 'Duración';
$string['schedulelegend'] = 'Leyenda del timeline';
$string['mainactivity'] = 'Actividad principal';
$string['subtypeactivity'] = 'Fase / Subtipo';
$string['course'] = 'Curso';
$string['coursetimerange'] = 'Periodo del curso';
$string['activities'] = 'Actividades';
$string['togglesubtasks'] = 'Expandir / colapsar subtareas';
$string['autosequencetitle'] = 'Autosecuenciar actividades';
$string['choosestrategy'] = 'Seleccione la estrategia de secuenciación';
$string['apply'] = 'Aplicar';
$string['strategy_equal'] = 'Reparto equitativo';
$string['strategy_equal_short'] = 'Reparte el tiempo del curso a partes iguales';
$string['strategy_equal_desc'] = 'Divide el tiempo total del curso a partes iguales entre todas las actividades principales. Las subtareas y fases se distribuyen equitativamente dentro de la ventana temporal de su actividad padre.';
$string['strategy_equal_tip'] = 'Recomendado para cursos estructurados por semanas, temas o bloques regulares.';
$string['strategy_sequential'] = 'Secuencial encadenado';
$string['strategy_sequential_short'] = 'Encadena actividades conservando su duración actual';
$string['strategy_sequential_desc'] = 'Coloca las actividades una tras otra consecutivamente sin huecos ni solapamientos, conservando la duración configurada de cada actividad a partir del inicio del curso.';
$string['strategy_sequential_tip'] = 'Recomendado cuando las actividades ya tienen asignada su duración real planificada (ej. 1 semana cada una).';
$string['strategy_proportional'] = 'Proporcional acotado';
$string['strategy_proportional_short'] = 'Distribución proporcional limitando valores extremos';
$string['strategy_proportional_desc'] = 'Escala las duraciones relativas para cubrir todo el curso respetando los pesos de cada actividad, acotando duraciones desproporcionadas para que ninguna absorba excesivo tiempo.';
$string['strategy_proportional_tip'] = 'Recomendado para actividades con distinta carga de trabajo que deben ajustarse al periodo del curso.';
$string['strategy_relative'] = 'Reescalado relativo';
$string['strategy_relative_short'] = 'Adapta la línea temporal al periodo del curso';
$string['strategy_relative_desc'] = 'Aplica el mismo desplazamiento y escalado a cada actividad editable, conservando los huecos, las duraciones relativas y las posiciones de la estructura temporal.';
$string['strategy_relative_tip'] = 'Úselo después de cambiar las fechas del curso cuando quiera conservar la estructura temporal existente.';
$string['activitybeforetimeline'] = 'La actividad comienza antes de la línea temporal visible.';
$string['activityaftertimeline'] = 'La actividad termina después de la línea temporal visible.';
$string['activitystartdisabled'] = 'La fecha de inicio está desactivada.';
$string['activityenddisabled'] = 'La fecha de fin está desactivada.';
$string['availabilitynoteditable'] = 'Las restricciones de disponibilidad de fecha son de solo lectura porque su lógica no es una combinación AND simple.';
$string['availabilityinvalid'] = 'La restricción de disponibilidad de fecha no es válida o ha cambiado. Recargue la página e inténtelo de nuevo.';
$string['availabilitychanged'] = 'La restricción de disponibilidad de fecha ya no existe. Recargue la página e inténtelo de nuevo.';
$string['availabilityinvalidrange'] = 'El final de un rango de disponibilidad debe ser posterior a su inicio.';
$string['availabilityrestriction'] = 'Restricción de disponibilidad de fecha';
$string['availabilityrestrictionhint'] = 'Arrastre el candado o sus manejadores para ajustar la restricción de fecha.';
$string['unmappedactivitynoteditable'] = 'Esta actividad no tiene fechas nativas reconocidas; solo se puede editar su restricción de disponibilidad.';
$string['dateinterval'] = 'Intervalo de fechas';
$string['kuetactivitynoteditable'] = 'El periodo de KUET se calcula a partir de sus sesiones. Edite las sesiones programadas para reprogramarlo.';
$string['kuetactivityderivedhint'] = 'Mueva o redimensione esta actividad para mover o redimensionar sus sesiones programadas.';
$string['noschedulablechanges'] = 'No hay cambios de fechas editables que guardar. Las fechas de KUET se almacenan en las sesiones programadas.';
$string['subactivityparentbounds'] = 'La subactividad debe permanecer dentro del periodo de la actividad padre.';
$string['close'] = 'Cerrar';
$string['editdatesmodal'] = 'Editar fechas de la actividad';
$string['editdatesmodaltip'] = 'Los cambios manuales se reflejarán automáticamente en el cronograma y la tabla.';
$string['invaliddateorder'] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
$string['clicktoeditdate'] = 'Haga clic para editar fecha y hora';
$string['timeclose'] = 'La fecha de cierre no puede ser anterior a la fecha de apertura';
$string['assesstimefinish'] = 'La fecha de fin de valoración no puede ser anterior a la de inicio';
$string['assesstimefrom'] = 'Valorar elementos enviados desde';
$string['assesstimeto'] = 'Valorar elementos enviados hasta';
$string['closedate'] = 'La fecha de cierre no puede ser anterior a la fecha de apertura';
$string['deadline'] = 'La fecha límite no puede ser anterior a la fecha de inicio disponible';
$string['dependentdate'] = 'Las fechas de inicio y fin deben estar ambas fijadas o ambas desactivadas';
$string['timedue'] = 'La fecha de entrega no puede ser anterior a la fecha disponible';
$string['timeend'] = 'La fecha límite no puede ser anterior a la fecha de inicio';
$string['noteditable'] = 'Esta actividad no se puede editar';
$string['kuetsessionnoteditable'] = 'Esta sesión manual de KUET no se puede editar desde el replanificador.';
$string['milestone'] = 'Hito';
$string['milestonedate'] = 'Fecha del hito';
$string['enablemilestone'] = 'Activar esta fecha';
