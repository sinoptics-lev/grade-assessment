import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router';
import { apiRequest } from '@/hooks/useApi';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Brain, Send, SkipForward, Loader2, AlertCircle, ChevronRight, Target, Lightbulb, Check } from 'lucide-react';
import type { Question } from '@/types';

export default function AssessmentPage() {
  const navigate = useNavigate();
  const [assessmentId, setAssessmentId] = useState(0);
  const [currentQuestion, setCurrentQuestion] = useState<Question | null>(null);
  const [questionNumber, setQuestionNumber] = useState(1);
  const [totalQuestions] = useState(15);
  const [answer, setAnswer] = useState('');
  const [selectedOptions, setSelectedOptions] = useState<string[]>([]);
  const [comment, setComment] = useState('');
  const [loading, setLoading] = useState(false);
  const [initialLoading, setInitialLoading] = useState(true);
  const [error, setError] = useState('');
  const [lastEvaluation, setLastEvaluation] = useState<{ score: number; max_score: number; feedback: string; covered_topics: string[]; missed_topics: string[] } | null>(null);
  const [showEvaluation, setShowEvaluation] = useState(false);
  const [completed, setCompleted] = useState(false);

  useEffect(() => {
    const id = sessionStorage.getItem('assessment_id');
    if (!id) { navigate('/'); return; }
    setAssessmentId(parseInt(id));
    loadQuestion(parseInt(id));
  }, []);

  const loadQuestion = async (id: number) => {
    setLoading(true);
    setError('');
    try {
      const response = await apiRequest('assessment', 'POST', { assessment_id: id }, { path: 'question' });
      const resp = response as Record<string, unknown>;
      if (resp.completed === true) { handleComplete(); return; }
      if (resp.question) {
        setCurrentQuestion(resp.question as Question);
        setQuestionNumber((resp.question_number as number) || 1);
        setAnswer(''); setSelectedOptions([]); setComment(''); setShowEvaluation(false); setLastEvaluation(null);
      } else { setError('Не удалось загрузить вопрос'); }
    } catch (err) { setError(err instanceof Error ? err.message : 'Ошибка'); }
    finally { setLoading(false); setInitialLoading(false); }
  };

  const hasOptions = !!(currentQuestion?.options && currentQuestion.options.length >= 3);
  const isMultiple = currentQuestion?.question_type === 'multiple';

  const toggleOption = (opt: string) => {
    if (loading) return;
    if (isMultiple) {
      setSelectedOptions(prev => prev.includes(opt) ? prev.filter(o => o !== opt) : [...prev, opt]);
    } else {
      setSelectedOptions([opt]);
    }
  };

  const handleSubmitAnswer = async () => {
    if (!currentQuestion) return;
    if (hasOptions && selectedOptions.length === 0) return;
    if (!hasOptions && !answer.trim()) return;
    setLoading(true); setError('');
    try {
      const response = await apiRequest('assessment', 'POST', {
        assessment_id: assessmentId, question: currentQuestion,
        selected_options: selectedOptions, comment: comment.trim(),
        answer: hasOptions ? '' : answer.trim()
      }, { path: 'answer' });
      const resp = response as Record<string, unknown>;
      const evaluation = resp.evaluation as Record<string, unknown> | undefined;
      if (evaluation) {
        setLastEvaluation({
          score: (resp.score as number) || 0, max_score: (resp.max_score as number) || 10,
          feedback: (evaluation.feedback as string) || '', covered_topics: (evaluation.covered_topics as string[]) || [],
          missed_topics: (evaluation.missed_topics as string[]) || []
        });
        setShowEvaluation(true);
      }
      setTimeout(() => {
        if (questionNumber >= totalQuestions) handleComplete();
        else loadQuestion(assessmentId);
      }, 2500);
    } catch (err) { setError(err instanceof Error ? err.message : 'Ошибка'); setLoading(false); }
  };

  const handleSkip = () => {
    if (questionNumber >= totalQuestions) handleComplete();
    else loadQuestion(assessmentId);
  };

  const handleComplete = async () => {
    setCompleted(true);
    try { await apiRequest('assessment', 'POST', { assessment_id: assessmentId }, { path: 'complete' }); } catch (e) {}
    setTimeout(() => {
      const token = sessionStorage.getItem('access_token');
      if (token) navigate(`/report?t=${token}`); else navigate('/');
    }, 2000);
  };

  const getDifficultyLabel = (level: number) => {
    const labels: Record<number, { text: string; color: string }> = {
      1: { text: 'Junior', color: 'bg-blue-100 text-blue-700' }, 2: { text: 'Middle', color: 'bg-green-100 text-green-700' },
      3: { text: 'Senior', color: 'bg-orange-100 text-orange-700' }, 4: { text: 'Lead', color: 'bg-purple-100 text-purple-700' }
    };
    return labels[level] || { text: 'Middle', color: 'bg-gray-100 text-gray-700' };
  };

  const getSkillTypeLabel = (type: string) =>
    type === 'hard' ? { text: 'Hard Skill', color: 'bg-indigo-100 text-indigo-700' } : { text: 'Soft Skill', color: 'bg-pink-100 text-pink-700' };

  if (initialLoading) return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
      <div className="text-center"><Loader2 className="w-12 h-12 animate-spin text-primary mx-auto mb-4" />
        <p className="text-lg text-slate-600">Генерация вопроса...</p></div>
    </div>);

  if (completed) return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
      <Card className="max-w-md w-full mx-4"><CardContent className="pt-8 pb-8 text-center">
        <div className="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <Target className="w-8 h-8 text-green-600" /></div>
        <h2 className="text-2xl font-bold mb-2">Оценка завершена!</h2>
        <Progress value={100} className="mb-2" /><p className="text-sm text-slate-500">Перенаправление...</p>
      </CardContent></Card></div>);

  const diffLabel = currentQuestion ? getDifficultyLabel(currentQuestion.difficulty) : null;
  const skillLabel = currentQuestion ? getSkillTypeLabel(currentQuestion.skill_type) : null;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <header className="bg-white border-b shadow-sm">
        <div className="max-w-4xl mx-auto px-4 py-4">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center gap-3"><Brain className="w-6 h-6 text-primary" />
              <h1 className="text-lg font-semibold">Оценка компетенций</h1></div>
            <div className="text-sm text-slate-500">Вопрос {questionNumber} из {totalQuestions}</div>
          </div>
          <Progress value={(questionNumber / totalQuestions) * 100} className="h-2" />
        </div>
      </header>

      <div className="max-w-4xl mx-auto px-4 py-8">
        <div className="grid lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <Card className="shadow-lg">
              <CardHeader className="pb-3">
                <div className="flex items-center justify-between flex-wrap gap-2">
                  <div className="flex items-center gap-2">
                    {diffLabel && <Badge className={diffLabel.color}>{diffLabel.text}</Badge>}
                    {skillLabel && <Badge className={skillLabel.color}>{skillLabel.text}</Badge>}
                  </div>
                  {currentQuestion?.category && <Badge variant="outline">{currentQuestion.category}</Badge>}
                </div>
              </CardHeader>
              <CardContent>
                {currentQuestion && <div className="mb-6">
                  <h2 className="text-xl font-semibold leading-relaxed">{currentQuestion.question}</h2>
                  {currentQuestion.reasoning && <p className="text-sm text-slate-500 mt-2 italic">{currentQuestion.reasoning}</p>}
                </div>}

                {!showEvaluation ? (
                  <div className="space-y-4">
                    {hasOptions ? (
                      <div className="space-y-2">
                        <p className="text-sm text-slate-500 mb-3">
                          {isMultiple ? 'Выберите все подходящие варианты:' : 'Выберите один вариант:'}
                        </p>
                        {currentQuestion!.options!.map((opt, i) => {
                          const selected = selectedOptions.includes(opt);
                          return (
                            <button key={i} type="button" onClick={() => toggleOption(opt)} disabled={loading}
                              className={`w-full text-left p-4 rounded-lg border-2 transition-all flex items-start gap-3 ${
                                selected ? 'border-primary bg-primary/5 shadow-sm' : 'border-slate-200 bg-white hover:border-slate-300 hover:bg-slate-50'
                              } ${loading ? 'opacity-60 cursor-not-allowed' : 'cursor-pointer'}`}>
                              <span className={`mt-0.5 flex-shrink-0 w-5 h-5 flex items-center justify-center border-2 ${
                                isMultiple ? 'rounded' : 'rounded-full'
                              } ${selected ? 'border-primary bg-primary text-white' : 'border-slate-300 bg-white'}`}>
                                {selected && <Check className="w-3.5 h-3.5" />}
                              </span>
                              <span className="text-base leading-relaxed">{opt}</span>
                            </button>
                          );
                        })}
                        <div className="pt-2">
                          <textarea className="w-full min-h-[60px] p-3 border rounded-lg resize-y focus:ring-2 focus:ring-primary text-sm"
                            placeholder="Дополните ответ (необязательно)..." value={comment}
                            onChange={(e) => setComment(e.target.value)} disabled={loading} />
                        </div>
                      </div>
                    ) : (
                      <textarea className="w-full min-h-[150px] p-4 border rounded-lg resize-y focus:ring-2 focus:ring-primary text-base"
                        placeholder="Введите ваш ответ подробно..." value={answer} onChange={(e) => setAnswer(e.target.value)} disabled={loading} />
                    )}
                    {error && <div className="flex items-center gap-2 text-red-600 text-sm bg-red-50 p-3 rounded-lg"><AlertCircle className="w-4 h-4" />{error}</div>}
                    <div className="flex gap-3">
                      <Button onClick={handleSubmitAnswer} disabled={(hasOptions ? selectedOptions.length === 0 : !answer.trim()) || loading} className="flex-1" size="lg">
                        {loading ? <Loader2 className="w-5 h-5 animate-spin mr-2" /> : <Send className="w-5 h-5 mr-2" />}Отправить
                      </Button>
                      <Button variant="outline" onClick={handleSkip} disabled={loading}><SkipForward className="w-5 h-5 mr-2" />Пропустить</Button>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-4">
                    {lastEvaluation && (
                      <div className="bg-slate-50 rounded-lg p-4 space-y-3">
                        <div className="flex items-center justify-between">
                          <h3 className="font-semibold flex items-center gap-2"><Lightbulb className="w-5 h-5 text-yellow-500" />Оценка</h3>
                          <Badge variant={lastEvaluation.score >= 7 ? 'default' : lastEvaluation.score >= 4 ? 'secondary' : 'destructive'} className="text-lg px-3">
                            {lastEvaluation.score}/{lastEvaluation.max_score}</Badge>
                        </div>
                        {lastEvaluation.feedback && <p className="text-slate-700">{lastEvaluation.feedback}</p>}
                        {lastEvaluation.covered_topics.length > 0 && <div><p className="text-sm font-medium text-green-700 mb-1">Освещённые темы:</p>
                          <div className="flex flex-wrap gap-1">{lastEvaluation.covered_topics.map((t, i) => <Badge key={i} variant="outline" className="bg-green-50">✓ {t}</Badge>)}</div></div>}
                        {lastEvaluation.missed_topics.length > 0 && <div><p className="text-sm font-medium text-orange-700 mb-1">Темы для изучения:</p>
                          <div className="flex flex-wrap gap-1">{lastEvaluation.missed_topics.map((t, i) => <Badge key={i} variant="outline" className="bg-orange-50">• {t}</Badge>)}</div></div>}
                        <div className="flex items-center gap-2 text-sm text-slate-500 pt-2"><Loader2 className="w-4 h-4 animate-spin" />Загрузка следующего вопроса...</div>
                      </div>)}
                  </div>)}
              </CardContent>
            </Card>
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
              <h4 className="font-semibold text-blue-900 mb-2">Советы</h4>
              <ul className="text-sm text-blue-800 space-y-1">
                <li>• Выбирайте варианты, которые лучше всего отражают вашу практику</li>
                <li>• При желании дополните ответ комментарием — это может повысить оценку</li>
                <li>• Если не уверены — отвечайте честно</li>
              </ul>
            </div>
          </div>
          <div className="space-y-4">
            <Card><CardHeader><CardTitle className="text-base">Прогресс</CardTitle></CardHeader>
              <CardContent className="space-y-4">
                <div><div className="flex justify-between text-sm mb-1"><span>Вопросы</span><span>{questionNumber}/{totalQuestions}</span></div>
                  <Progress value={(questionNumber / totalQuestions) * 100} /></div>
                <div className="text-sm text-slate-600"><ChevronRight className="w-4 h-4 inline" />~{totalQuestions - questionNumber} вопросов осталось</div>
              </CardContent></Card>
            {currentQuestion?.expected_topics && <Card><CardHeader><CardTitle className="text-base">Темы</CardTitle></CardHeader>
              <CardContent><div className="flex flex-wrap gap-2">{currentQuestion.expected_topics.map((t, i) => <Badge key={i} variant="secondary">{t}</Badge>)}</div></CardContent></Card>}
          </div>
        </div>
      </div>
    </div>
  );
}
