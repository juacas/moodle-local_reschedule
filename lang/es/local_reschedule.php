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
$string['reschedulemodule'] = 'Replanificar esta actividad';
$string['rescheduletitle'] = 'Replanificación y Línea Temporal del Curso';
$string['effortdrops'] = 'Drops de esfuerzo';
$string['effortscale'] = 'Esfuerzo semanal';
$string['effortweeklyunit'] = 'h/semana';
$string['effortsettings'] = 'Configurar gráfico de esfuerzo';
$string['effortlabel'] = 'Esfuerzo';
$string['effortpoints'] = 'pts';
$string['efforttabletitle'] = 'Tabla de esfuerzos';
$string['efforttablehelp'] = 'Introduce las horas de esfuerzo estimado de cada elemento. Una hora equivale a un punto de esfuerzo. Las actividades con subactividades muestran la suma de estas; las sumas, los valores calculados por un adaptador y los hitos puntuales no se pueden editar.';
$string['efforttablehours'] = 'Esfuerzo estimado (horas)';
$string['efforttablesource'] = 'Origen';
$string['efforttableadapter'] = 'Adaptador de actividad';
$string['efforttablechildren'] = 'Suma de subactividades';
$string['efforttabledefault'] = 'Estimación editable';
$string['efforttablemanual'] = 'Editado en esta visita';
$string['efforttablemilestone'] = 'Hito puntual';
$string['efforttablenotpersisted'] = 'Los cambios se aplican al Gantt y a la gráfica solo durante esta visita. Se perderán al recargar la página; el botón de guardar fechas no los almacena.';
$string['efforttableinvalid'] = 'Introduce un número de horas mayor o igual que cero en cada fila editable.';
$string['efforttableapply'] = 'Aplicar esfuerzos';
$string['effortmodeltitle'] = 'Modelo de esfuerzo diario';
$string['effortmodelchoose'] = 'Elige un modelo';
$string['effortmodelreal'] = 'Realista: presión del plazo';
$string['effortmodelrealshort'] = 'Ley de Parkinson y síndrome del estudiante';
$string['effortmodelrealdesc'] = 'El esfuerzo crece lentamente al principio, se acelera al acercarse la entrega y alcanza el máximo al final de la actividad. Después sigue un breve decaimiento exponencial. Representa el trabajo que llena el tiempo disponible y la urgencia tardía.';
$string['effortmodelideal'] = 'Ideal: ritmo planificado';
$string['effortmodelidealshort'] = 'Curva en S con esfuerzo diario en campana';
$string['effortmodelidealdesc'] = 'El trabajo acumulado sigue una curva en S. El esfuerzo diario crece, llega al máximo hacia la mitad y luego disminuye hasta la entrega.';
$string['effortmodelnote'] = 'Ambos modelos conservan el esfuerzo asignado a cada actividad fechada y añaden un breve decaimiento exponencial tras su final. La elección se guarda en este navegador.';
$string['effortchallengeheading'] = 'El desafío de la distribución del tiempo en el aula';
$string['effortchallengeintro'] = 'La asignación de una tarea escolar abre una ventana de tiempo en la que interactúan dos ideas de gestión: la Ley de Parkinson (Parkinson, 1957) y el Síndrome del Estudiante (Goldratt, 1997). La primera describe cómo el trabajo tiende a expandirse hasta ocupar el plazo disponible; el segundo, cómo se puede posponer el esfuerzo hasta que la fecha límite parece urgente.';
$string['effortchallengeevidence'] = 'La investigación relaciona los plazos lejanos con una mayor tendencia a posponer las tareas (Steel, 2007). En estudios longitudinales con estudiantes, la procrastinación se asoció con más estrés al final del curso y notas más bajas (Tice y Baumeister, 1997). El aumento exponencial de la gráfica es un modelo ilustrativo: no establece una concentración universal en las últimas 48 horas.';
$string['effortchallengeaction'] = 'Para distribuir el esfuerzo de forma más saludable, conviene diseñar entregas parciales y secuenciales. Los plazos intermedios pueden reducir la procrastinación y mejorar el rendimiento (Ariely y Wertenbroch, 2002); la práctica distribuida también favorece la retención de lo aprendido (Cepeda et al., 2006).';
$string['effortchallengerefs'] = 'Referencias';
$string['rescheduledesc'] = 'Arrastre las actividades o ajuste sus extremos de inicio y fin para sincronizar la secuencia dentro del periodo del curso.';
$string['mapping'] = 'Reglas de mapeo de fechas de actividades';
$string['mapping_desc'] = 'Edita visualmente las reglas de fechas del Gantt o su JSON. Las actividades con adaptador propio se gestionan en código.';
$string['mappingeditor'] = 'Editor visual de reglas';
$string['mappingmodule'] = 'Actividad o tabla';
$string['mappingkind'] = 'Tipo de fecha';
$string['mappingrange'] = 'Intervalo';
$string['mappingmilestone'] = 'Hito puntual';
$string['mappingopen'] = 'Solo inicio';
$string['mappingtitlefield'] = 'Campo de título';
$string['mappinglabel'] = 'Etiqueta del Gantt';
$string['mappingstart'] = 'Campo de inicio o hito';
$string['mappingend'] = 'Campo de fin';
$string['mappingparent'] = 'Actividad padre (opcional)';
$string['mappingforeignkey'] = 'Campo de ID del padre';
$string['mappingoptional'] = 'Fecha opcional';
$string['mappingdateonly'] = 'Solo fecha';
$string['mappingboundstart'] = 'Limitar al inicio del padre';
$string['mappingboundend'] = 'Limitar al fin del padre';
$string['mappingavailability'] = 'Restricciones de disponibilidad';
$string['mappingeffortmodel'] = 'Estimador de esfuerzo por defecto';
$string['mappingeffortdefault'] = 'Valor del adaptador de actividad';
$string['mappingeffortfixed'] = 'Horas fijas';
$string['mappingeffortperday'] = 'Horas por día de duración';
$string['mappingefforthours'] = 'Horas estimadas';
$string['mappingefforthoursperday'] = 'Horas por día';
$string['mappingeditable'] = 'Permitir editar fechas';
$string['mappingadd'] = 'Añadir regla';
$string['mappingremove'] = 'Eliminar';
$string['mappingsuggestions'] = 'Sugerencias de los campos de fecha de mod_form instalados';
$string['mappingraw'] = 'Editar JSON directamente';
$string['mappingoriginform'] = 'selector de fecha en mod_form';
$string['mappingoriginschema'] = 'heurística de campo de base de datos';
$string['mappinginvalidjson'] = 'Introduce un array JSON de reglas.';
$string['mappinginvalidrules'] = 'Una o varias reglas tienen campos o identificadores inválidos.';
$string['mappingownedrule'] = 'Esta actividad tiene un adaptador de código y no se configura aquí.';
$string['mappingmodule_help'] = 'Nombre de la tabla de la actividad instalada. Para una subactividad guardada en otra tabla, escribe el nombre de esa tabla y selecciona también su actividad padre.';
$string['mappingkind_help'] = 'Intervalo usa inicio y fin; hito puntual representa una sola fecha; solo inicio deja el periodo abierto sin fecha de fin. Los hitos no aportan esfuerzo a la gráfica.';
$string['mappingtitlefield_help'] = 'Columna de la tabla que contiene el nombre mostrado para cada fila del Gantt; normalmente name o title.';
$string['mappinglabel_help'] = 'Texto que identifica este tipo de fila o fase en el Gantt. No cambia el nombre de la actividad en Moodle.';
$string['mappingstart_help'] = 'Columna que guarda la fecha y hora de inicio del intervalo, o la fecha única si el tipo es hito puntual.';
$string['mappingend_help'] = 'Columna que guarda el final del intervalo. Solo se usa cuando el tipo es intervalo.';
$string['mappingparent_help'] = 'Nombre de la tabla de la actividad principal a la que pertenece esta fila. Déjalo vacío para crear una actividad principal; su regla debe existir en este JSON.';
$string['mappingforeignkey_help'] = 'En una tabla hija, columna que contiene el ID del registro padre. Si se omite, se supone el nombre de la tabla padre seguido de id. No se usa cuando la fase está en la misma tabla.';
$string['mappingoptional_help'] = 'Permite dejar sin fecha un hito opcional y volver a activarlo desde el Gantt.';
$string['mappingdateonly_help'] = 'Indica que la fecha de un hito no incluye hora. Al moverlo se ajusta al comienzo del día local.';
$string['mappingboundstart_help'] = 'Si está activado, una subactividad no puede empezar antes que su actividad padre. Está activado por defecto.';
$string['mappingboundend_help'] = 'Si está activado, una subactividad no puede terminar después que su actividad padre. Está activado por defecto.';
$string['mappingavailability_help'] = 'Automático muestra y edita las restricciones de disponibilidad por fecha de Moodle en la fila principal. Desactivado oculta esa capa en el Gantt.';
$string['mappingeditable_help'] = 'Permite modificar las fechas nativas de esta fila desde el Gantt. Desactívalo para mostrarla en solo lectura; la edición del curso sigue siendo necesaria en todos los casos.';
$string['mappingeffortmodel_help'] = 'Elige el esfuerzo inicial de esta regla: valor del adaptador, horas fijas o una tasa de horas por cada 24 horas de duración. El estimador proporcional requiere un intervalo con ambas fechas.';
$string['mappingefforthours_help'] = 'Esfuerzo fijo de cada elemento en horas. Una hora equivale a un punto de esfuerzo en la gráfica.';
$string['mappingefforthoursperday_help'] = 'Horas de esfuerzo por cada 24 horas entre las fechas reales de inicio y fin; se incluyen fracciones de día. Se recalcula al cambiar la duración.';
$string['mappingraw_help'] = 'Representación JSON de las mismas reglas del editor visual. Sirve para editar tablas o campos avanzados; al salir del campo se actualiza la vista visual si el JSON es válido.';
$string['autosequence'] = 'Auto-secuenciar';
$string['weekify'] = 'Weekify';
$string['weekifytitle'] = 'Mover actividades a sus semanas';
$string['weekifyintro'] = 'Weekify mueve cada actividad mostrada a la semana existente del curso que contiene su fecha de inicio guardada. Si la actividad no tiene fecha de inicio, usa la fecha más temprana de sus subactividades. No cambia las fechas de las actividades.';
$string['weekifynote'] = 'Revisa los movimientos antes de confirmar. Se omiten las actividades sin fecha de inicio o sin una semana existente correspondiente. Mover una actividad a una semana oculta puede ocultarla.';
$string['weekifyconfirm'] = 'Mover actividades';
$string['weekifysavefirst'] = 'Guarda o restablece los cambios de fechas pendientes antes de usar Weekify.';
$string['weekifyloading'] = 'Comprobando las secciones semanales…';
$string['weekifymoves'] = 'actividades para mover';
$string['weekifyundated'] = 'sin fecha de inicio';
$string['weekifyoutofrange'] = 'fuera de las semanas existentes';
$string['weekifyalready'] = 'ya situadas en la semana correcta';
$string['weekifyunsupported'] = 'que no pueden moverse';
$string['weekifymore'] = 'movimientos más';
$string['weekifymoved'] = 'Actividades movidas';
$string['weekifyfailed'] = 'Actividades que no se pudieron mover';
$string['weekifyerror'] = 'No se pudo completar Weekify. Inténtalo de nuevo.';
$string['weekifynotweeks'] = 'Weekify solo está disponible en cursos por semanas.';
$string['resetschedule'] = 'Restablecer';
$string['unsavedchanges'] = 'Tiene cambios pendientes de guardar.';
$string['schedulesaved'] = 'La programación de actividades se ha guardado correctamente.';
$string['error_saving'] = 'Se produjo un error al guardar la programación.';
$string['error_saving_header'] = 'No se pudo guardar la programación';
$string['error_saving_affected'] = 'Actividades que debe revisar en la tabla:';
$string['error_saving_pending'] = 'Actividades con cambios pendientes en la tabla:';
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
$string['autosequenceonlydated'] = 'Solo se distribuyen las actividades con fecha de inicio y fin definidas. Las filas con alguna fecha ausente conservan su estado.';
$string['autosequenceavoidblackout'] = 'Evitar días de la lista negra (fines de semana)';
$string['autosequenceavoidblackoutinfo'] = 'Sitúa las fechas calculadas de inicio y fin en días laborables. Se omiten los fines de semana; si la estrategia no cabe en el curso o en la actividad padre, se informa del motivo.';
$string['autosequenceblackoutempty'] = 'No hay tiempo laborable en el intervalo del curso. No se aplicó la secuenciación.';
$string['autosequencenoitems'] = 'No hay actividades aptas con ambas fechas definidas ni restricciones de disponibilidad completas para secuenciar.';
$string['autosequencenospace'] = 'La estrategia necesita {required}, pero el intervalo del curso solo dispone de {available}. Acorte las actividades, amplíe las fechas del curso o elija otra estrategia.';
$string['autosequencenoworkingspace'] = 'La estrategia necesita {required} de tiempo laborable, pero solo quedan {available} al excluir los días de la lista negra. Acorte las actividades, amplíe el curso o cambie la opción de exclusión.';
$string['autosequenceparentspace'] = 'Las subactividades de «{activity}» necesitan {required}, pero el intervalo de la actividad padre solo tiene {available}. Acorte las subactividades o amplíe la actividad padre.';
$string['autosequenceparentmissing'] = 'No se puede secuenciar la subactividad seleccionada «{activity}» porque su actividad padre no tiene un intervalo de fechas completo.';
$string['autosequencenospan'] = 'Las fechas seleccionadas no abarcan un intervalo que se pueda escalar. Defina una fecha de inicio y otra de fin para al menos una actividad.';
$string['autosequenceoverflowtitle'] = 'Si las actividades no caben (secuencial encadenado)';
$string['autosequenceoverflowstop'] = 'Detener y explicar el problema';
$string['autosequenceoverflowshorten'] = 'Acortar las últimas tareas para ajustarlas al curso';
$string['autosequenceoverflowkeep'] = 'Conservar la duración y no mover las últimas tareas';
$string['autosequenceoverflowhelp'] = 'Se mantiene al menos una hora por actividad y el espacio mínimo de sus subactividades. Si ni así caben, la operación se detiene. Las tareas conservadas no cambian de fecha.';
$string['editmoderequired'] = 'Active el modo de edición del curso para cambiar las fechas de las actividades.';
$string['readonlynotice'] = 'Vista de solo lectura. Active el modo de edición para reprogramar actividades.';
$string['choosestrategy'] = 'Seleccione la estrategia de secuenciación';
$string['apply'] = 'Aplicar';
$string['strategy_equal'] = 'Reparto equitativo';
$string['strategy_equal_short'] = 'Reparte el tiempo del curso a partes iguales';
$string['strategy_equal_desc'] = 'Divide el tiempo del curso a partes iguales entre las actividades principales con ambas fechas definidas. Las subtareas y fases fechadas se distribuyen dentro del periodo de su actividad padre.';
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
$string['selectionmode'] = 'Modo de selección';
$string['snapto'] = 'Snap:';
$string['snaphours'] = 'horas';
$string['snapdays'] = 'días';
$string['selectiondraghint'] = 'Arrastre las barras para ajustar la planificación';
$string['selectionselected'] = 'seleccionadas';
$string['selectactivity'] = 'Seleccionar actividad';
$string['selectionempty'] = 'Seleccione al menos una actividad primero.';
$string['selectionweekifyempty'] = 'Seleccione una fila de actividad del curso para usar Weekify.';
$string['unmappedactivitynoteditable'] = 'Esta actividad no tiene fechas nativas reconocidas; solo se puede editar su restricción de disponibilidad.';
$string['dateinterval'] = 'Intervalo de fechas';
$string['kuetactivitynoteditable'] = 'El periodo de KUET se calcula a partir de sus sesiones. Edite las sesiones programadas para reprogramarlo.';
$string['kuetactivityderivedhint'] = 'Mueva o redimensione esta actividad para mover o redimensionar sus sesiones programadas.';
$string['noschedulablechanges'] = 'No hay cambios de fechas editables que guardar. Las fechas de KUET se almacenan en las sesiones programadas.';
$string['subactivityparentbounds'] = 'La subactividad debe permanecer dentro del periodo de la actividad padre.';
$string['close'] = 'Cerrar';
$string['editdatesmodal'] = 'Editar fechas de la actividad';
$string['editdatesmodaltip'] = 'Desmarque una casilla para quitar esa fecha. Los cambios se reflejarán en el cronograma y la tabla.';
$string['invaliddateorder'] = 'La fecha de fin debe ser posterior a la fecha de inicio.';
$string['datefieldrequired'] = 'Cada fecha activada debe tener un valor válido.';
$string['enablestartdate'] = 'Activar fecha de inicio';
$string['enableenddate'] = 'Activar fecha de fin';
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
