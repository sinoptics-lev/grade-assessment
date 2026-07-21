import { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router';
import { apiRequest } from '@/hooks/useApi';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Progress } from '@/components/ui/progress';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Award, TrendingUp, Brain, Target, BookOpen, Download, ChevronLeft, Star, AlertTriangle, CheckCircle, BarChart3, Lightbulb, Zap } from 'lucide-react';
import type { ReportData } from '@/types';
import { LEVEL_BG_COLORS, SKILL_LEVEL_LABELS, SKILL_LEVEL_COLORS } from '@/types';

export default function ReportPage() {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [report, setReport] = useState<ReportData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const token = searchParams.get('t') || searchParams.get('token') || '';

  useEffect(() => {
    if (!token) {
      const saved = sessionStorage.getItem('access_token');
      if (saved) loadReport(saved); else { setError('Токен не найден'); setLoading(false); }
    } else loadReport(token);
  }, [token]);

  const loadReport = async (accessToken: string) => {
    try {
      const response = await apiRequest('report', 'GET', undefined, { path: 'by-token', token: accessToken });
      const resp = response as Record<string, unknown>;
      if (resp.success === true && resp.report) setReport(resp.report as ReportData);
      else setError('Не удалось загрузить отчёт');
    } catch (err) { setError(err instanceof Error ? err.message : 'Ошибка'); }
    finally { setLoading(false); }
  };

  const handleExport = (format: string) => { if (token) window.open(`/api/report/export?format=${format}&token=${token}`, '_blank'); };

  if (loading) return <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
    <div className="text-center"><Brain className="w-12 h-12 animate-pulse text-primary mx-auto mb-4" /><p className="text-lg text-slate-600">Формирование отчёта...</p></div></div>;

  if (error) return <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100 flex items-center justify-center">
    <Card className="max-w-md w-full mx-4"><CardContent className="pt-8 pb-8 text-center">
      <AlertTriangle className="w-12 h-12 text-orange-500 mx-auto mb-4" /><h2 className="text-xl font-bold mb-2">Ошибка</h2><p className="text-slate-600 mb-4">{error}</p>
      <Button onClick={() => navigate('/')}><ChevronLeft className="w-4 h-4 mr-2" />На главную</Button>
    </CardContent></Card></div>;

  if (!report) return null;

  const { assessment_info, summary, skill_matrix, analysis, answers_detail } = report;
  const levelCode = summary.final_level.code;

  return (
    <div className="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <header className="bg-white border-b shadow-sm">
        <div className="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between">
          <Button variant="ghost" onClick={() => navigate('/')}><ChevronLeft className="w-4 h-4 mr-2" />На главную</Button>
          <Button variant="outline" size="sm" onClick={() => handleExport('csv')}><Download className="w-4 h-4 mr-2" />CSV</Button>
        </div>
      </header>

      <div className="max-w-6xl mx-auto px-4 py-8">
        <div className={`rounded-2xl p-8 mb-8 border-2 ${LEVEL_BG_COLORS[levelCode]}`}>
          <div className="flex flex-col md:flex-row items-center gap-6">
            <div className="w-24 h-24 bg-white rounded-full flex items-center justify-center shadow-lg">
              <Award className="w-12 h-12 text-primary" /></div>
            <div className="flex-1 text-center md:text-left">
              <h1 className="text-3xl font-bold mb-2">{assessment_info.full_name}</h1>
              <p className="text-lg text-slate-600 mb-4">{assessment_info.position} {assessment_info.experience_years > 0 && `· ${assessment_info.experience_years} лет`}</p>
              <div className="flex flex-wrap items-center justify-center md:justify-start gap-3">
                <Badge className="text-lg px-4 py-1.5">{summary.final_level.name}</Badge>
                <div className="flex items-center gap-2"><Progress value={summary.percentage} className="w-32" /><span className="font-semibold">{summary.percentage}%</span></div>
              </div></div>
            <div className="text-center"><div className="text-4xl font-bold">{summary.total_score}/{summary.max_score}</div><div className="text-sm text-slate-500">общий балл</div></div>
          </div></div>

        <div className="grid lg:grid-cols-3 gap-6">
          <div className="lg:col-span-2 space-y-6">
            <Tabs defaultValue="skills" className="w-full">
              <TabsList className="grid w-full grid-cols-3">
                <TabsTrigger value="skills"><BarChart3 className="w-4 h-4 mr-2" />Матрица</TabsTrigger>
                <TabsTrigger value="analysis"><Zap className="w-4 h-4 mr-2" />Анализ</TabsTrigger>
                <TabsTrigger value="answers"><BookOpen className="w-4 h-4 mr-2" />Ответы</TabsTrigger>
              </TabsList>

              <TabsContent value="skills" className="space-y-4">
                <Card><CardHeader><CardTitle className="flex items-center gap-2"><Target className="w-5 h-5 text-indigo-500" />Hard Skills <Badge variant="outline">{skill_matrix.hard.average}/10</Badge></CardTitle></CardHeader>
                  <CardContent className="space-y-4">{skill_matrix.hard.skills.map((skill) => (
                    <div key={skill.id || skill.skill_name}>
                      <div className="flex items-center justify-between mb-1">
                        <span className="font-medium">{skill.skill_name}</span>
                        <div className="flex items-center gap-2"><Badge className={SKILL_LEVEL_COLORS[skill.level]}>{SKILL_LEVEL_LABELS[skill.level]}</Badge><span className="text-sm font-semibold w-12 text-right">{skill.score}/10</span></div>
                      </div><Progress value={(skill.score / skill.max_score) * 100} className="h-2" />
                      {skill.recommendations && <p className="text-xs text-slate-500 mt-1">{skill.recommendations}</p>}
                    </div>))}</CardContent></Card>

                <Card><CardHeader><CardTitle className="flex items-center gap-2"><Brain className="w-5 h-5 text-pink-500" />Soft Skills <Badge variant="outline">{skill_matrix.soft.average}/10</Badge></CardTitle></CardHeader>
                  <CardContent className="space-y-4">{skill_matrix.soft.skills.map((skill) => (
                    <div key={skill.id || skill.skill_name}>
                      <div className="flex items-center justify-between mb-1">
                        <span className="font-medium">{skill.skill_name}</span>
                        <div className="flex items-center gap-2"><Badge className={SKILL_LEVEL_COLORS[skill.level]}>{SKILL_LEVEL_LABELS[skill.level]}</Badge><span className="text-sm font-semibold w-12 text-right">{skill.score}/10</span></div>
                      </div><Progress value={(skill.score / skill.max_score) * 100} className="h-2" />
                    </div>))}</CardContent></Card>
              </TabsContent>

              <TabsContent value="analysis" className="space-y-4">
                <Card><CardHeader><CardTitle>Баланс навыков</CardTitle></CardHeader><CardContent>
                  <div className="grid grid-cols-2 gap-4 mb-4">
                    <div className="text-center p-4 bg-indigo-50 rounded-lg"><Target className="w-8 h-8 text-indigo-500 mx-auto mb-2" /><div className="text-2xl font-bold">{analysis.hard_vs_soft.hard_score}</div><div className="text-sm text-slate-600">Hard</div></div>
                    <div className="text-center p-4 bg-pink-50 rounded-lg"><Brain className="w-8 h-8 text-pink-500 mx-auto mb-2" /><div className="text-2xl font-bold">{analysis.hard_vs_soft.soft_score}</div><div className="text-sm text-slate-600">Soft</div></div>
                  </div>
                  <div className="text-center">
                    {analysis.hard_vs_soft.balance === 'hard_dominant' && <Badge variant="outline" className="text-indigo-700"><TrendingUp className="w-4 h-4 mr-1" />Hard преобладают</Badge>}
                    {analysis.hard_vs_soft.balance === 'soft_dominant' && <Badge variant="outline" className="text-pink-700"><TrendingUp className="w-4 h-4 mr-1" />Soft преобладают</Badge>}
                    {analysis.hard_vs_soft.balance === 'balanced' && <Badge variant="outline" className="text-green-700"><CheckCircle className="w-4 h-4 mr-1" />Сбалансированно</Badge>}
                  </div></CardContent></Card>

                {analysis.strengths.length > 0 && <Card><CardHeader><CardTitle className="flex items-center gap-2 text-green-700"><Star className="w-5 h-5" />Сильные стороны</CardTitle></CardHeader><CardContent className="space-y-3">
                  {analysis.strengths.map((s, i) => <div key={i} className="flex items-start gap-3 p-3 bg-green-50 rounded-lg">
                    <CheckCircle className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" /><div><div className="font-medium">{s.name}</div><div className="text-sm text-slate-600">{s.description}</div><Badge variant="outline" className="mt-1">{s.score}/10</Badge></div></div>)}
                </CardContent></Card>}

                {analysis.weaknesses.length > 0 && <Card><CardHeader><CardTitle className="flex items-center gap-2 text-orange-700"><Lightbulb className="w-5 h-5" />Области для развития</CardTitle></CardHeader><CardContent className="space-y-3">
                  {analysis.weaknesses.map((w, i) => <div key={i} className="flex items-start gap-3 p-3 bg-orange-50 rounded-lg">
                    <TrendingUp className="w-5 h-5 text-orange-600 mt-0.5 flex-shrink-0" /><div><div className="font-medium">{w.name}</div><div className="text-sm text-slate-600">{w.recommendation}</div><Badge variant="outline" className="mt-1">{w.score}/10</Badge></div></div>)}
                </CardContent></Card>}
              </TabsContent>

              <TabsContent value="answers" className="space-y-4">
                {answers_detail.map((ans, i) => <Card key={i}><CardContent className="pt-6">
                  <div className="flex items-center gap-2 mb-3">
                    <Badge variant="outline">#{i + 1}</Badge>
                    <Badge className={ans.skill_type === 'hard' ? 'bg-indigo-100 text-indigo-700' : 'bg-pink-100 text-pink-700'}>{ans.skill_type === 'hard' ? 'Hard' : 'Soft'}</Badge>
                    <Badge variant={ans.score >= 7 ? 'default' : ans.score >= 4 ? 'secondary' : 'destructive'}>{ans.score}/{ans.max_score}</Badge>
                  </div>
                  <p className="font-medium mb-2">{ans.question}</p>
                  <p className="text-sm text-slate-600 mb-3 bg-slate-50 p-3 rounded whitespace-pre-line">{ans.answer}</p>
                  {ans.feedback && <p className="text-sm text-slate-500 italic">{ans.feedback}</p>}
                </CardContent></Card>)}
              </TabsContent>
            </Tabs>
          </div>

          <div className="space-y-4">
            <Card><CardHeader><CardTitle className="text-base">Информация</CardTitle></CardHeader>
              <CardContent className="space-y-3 text-sm">
                <div className="flex justify-between"><span className="text-slate-600">Позиция</span><span className="font-medium">{assessment_info.position}</span></div>
                {assessment_info.department && <div className="flex justify-between"><span className="text-slate-600">Департамент</span><span className="font-medium">{assessment_info.department}</span></div>}
                <div className="flex justify-between"><span className="text-slate-600">Вопросов</span><span className="font-medium">{summary.questions_answered}</span></div>
                <div className="flex justify-between"><span className="text-slate-600">Дата</span><span className="font-medium">{new Date(assessment_info.created_at).toLocaleDateString('ru-RU')}</span></div>
              </CardContent></Card>
            <Card><CardHeader><CardTitle className="text-base">Рекомендации</CardTitle></CardHeader><CardContent className="space-y-3">
              <div className="flex items-start gap-2"><BookOpen className="w-4 h-4 text-blue-500 mt-0.5" /><p className="text-sm">Изучите материалы по навыкам с оценкой ниже 6</p></div>
              <div className="flex items-start gap-2"><Target className="w-4 h-4 text-green-500 mt-0.5" /><p className="text-sm">Развивайте сильные стороны</p></div>
              <div className="flex items-start gap-2"><Zap className="w-4 h-4 text-orange-500 mt-0.5" /><p className="text-sm">Практикуйте ответы среднего уровня</p></div>
            </CardContent></Card>
            <Button className="w-full" variant="outline" onClick={() => navigate('/')}><ChevronLeft className="w-4 h-4 mr-2" />Новая оценка</Button>
          </div>
        </div>
      </div>
    </div>
  );
}
